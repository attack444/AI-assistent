<?php

class Meow_MWAI_Query_Function {
  public string $name;
  public string $description;
  public array $parameters;
  public string $type; // 'code-engine', etc...
  public string $target; // 'server' or 'client'
  public string $behavior; // 'dynamic' (default) or 'static'
  public ?string $id;
  // Raw JSON Schema (MCP-style inputSchema). When set, it is sent to the
  // providers as-is and $parameters is ignored, so rich schemas (nested
  // objects, oneOf, items types) survive without being flattened.
  public ?array $rawSchema = null;

  public function __construct(
    string $name,
    string $description,
    array $parameters = [],
    ?string $type = null,
    ?string $id = null,
    ?string $target = null,
    ?string $behavior = null
  ) {
    // $name: The name of the function to be called.
    // Must be a-z, A-Z, 0-9, or contain underscores and dashes, with a maximum length of 64.
    if ( !preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $name ) ) {
      throw new InvalidArgumentException( "AI Engine: Invalid function name ($name) for Meow_MWAI_Query_Function." );
    }

    foreach ( $parameters as $parameter ) {
      if ( !( $parameter instanceof Meow_MWAI_Query_Parameter ) ) {
        throw new InvalidArgumentException( 'AI Engine: Invalid parameter for Meow_MWAI_Query_Function.' );
      }
    }

    $this->name = $name;
    $this->description = $description;
    $this->parameters = $parameters;
    $this->type = $type ?? 'manual';
    $this->id = $id;
    $this->target = $target ?? 'server';
    $this->behavior = ( $behavior === 'static' ) ? 'static' : 'dynamic';
  }

  public static function from_raw_schema(
    string $name,
    string $description,
    ?array $schema,
    ?string $type = null,
    ?string $id = null
  ): Meow_MWAI_Query_Function {
    $function = new self( $name, $description, [], $type, $id );
    $function->rawSchema = is_array( $schema ) ? $schema : null;
    return $function;
  }

  private function normalized_raw_schema(): array {
    $schema = $this->rawSchema;
    unset( $schema['$schema'] );
    if ( empty( $schema['type'] ) ) {
      $schema['type'] = 'object';
    }
    // An empty PHP array encodes as [], but every provider requires {} here.
    if ( empty( $schema['properties'] ) ) {
      $schema['properties'] = new stdClass();
    }
    if ( isset( $schema['required'] ) && empty( $schema['required'] ) ) {
      unset( $schema['required'] );
    }
    return $schema;
  }

  public function serializeForOpenAI() {
    if ( $this->rawSchema !== null ) {
      return [
        'name' => $this->name,
        'description' => $this->description,
        'parameters' => $this->normalized_raw_schema(),
      ];
    }

    // Initialize the base structure with name and description
    $json = [ 'name' => $this->name, 'description' => $this->description ];

    $properties = [];
    $required = [];

    // Loop through each parameter to construct the properties object
    if ( !empty( $this->parameters ) ) {
      foreach ( $this->parameters as $parameter ) {

        $properties[$parameter->name] = [
          'type' => $parameter->type, // Assuming each parameter has a 'type' attribute
          'description' => $parameter->description, // Assuming each parameter has a 'description' attribute
        ];

        // If the parameter type is "array" and has a "items" attribute, include it
        if ( $parameter->type === 'array' ) {
          $properties[$parameter->name]['items'] = [
            'type' => 'string', // Assuming the items are strings
          ];
        }

        // If an enum is set for the parameter, include it
        if ( isset( $parameter->enum ) ) {
          $properties[$parameter->name]['enum'] = $parameter->enum;
        }

        // If the parameter is required, add its name to the required array
        if ( $parameter->required ) {
          $required[] = $parameter->name;
        }
      }
    }

    // Always include parameters field (required by some APIs like OVH even when empty)
    // Properties must be an object (stdClass) when empty, not an empty array
    $json['parameters'] = [
      'type' => 'object',
      'properties' => empty( $properties ) ? new stdClass() : $properties,
      'required' => $required,
    ];

    return $json;
  }

  public function serializeForAnthropic() {
    if ( $this->rawSchema !== null ) {
      return [
        'name' => $this->name,
        'description' => $this->description,
        'input_schema' => $this->normalized_raw_schema(),
      ];
    }

    $json = [
      'name' => $this->name,
      'description' => $this->description,
      'input_schema' => [
        'type' => 'object',
        'properties' => new stdClass()
      ],
    ];

    if ( !empty( $this->parameters ) ) {
      $properties = [];
      $required = [];
      foreach ( $this->parameters as $parameter ) {
        $properties[$parameter->name] = [
          'type' => $parameter->type,
          'description' => $parameter->description,
        ];
        if ( isset( $parameter->enum ) ) {
          $properties[$parameter->name]['enum'] = $parameter->enum;
        }
        if ( $parameter->required ) {
          $required[] = $parameter->name;
        }
      }
      $json['input_schema']['properties'] = empty( $properties ) ? new stdClass() : $properties;
      if ( !empty( $required ) ) {
        $json['input_schema']['required'] = $required;
      }
    }

    return $json;
  }

  public function serializeForGemini() {
    if ( $this->rawSchema !== null ) {
      return [
        'name' => $this->name,
        'description' => $this->description,
        'parameters' => $this->normalized_raw_schema(),
      ];
    }

    $json = [
      'name' => $this->name,
      'description' => $this->description,
      'parameters' => [
        'type' => 'object',
        'properties' => new stdClass(),
        'required' => []
      ]
    ];

    if ( !empty( $this->parameters ) ) {
      $properties = [];
      $required = [];
      foreach ( $this->parameters as $parameter ) {
        $properties[$parameter->name] = [
          'type' => $parameter->type,
          'description' => $parameter->description,
        ];

        // If the parameter type is "array" and has a "items" attribute, include it
        if ( $parameter->type === 'array' ) {
          $properties[$parameter->name]['items'] = [
            'type' => 'string',
          ];
        }

        if ( isset( $parameter->enum ) ) {
          $properties[$parameter->name]['enum'] = $parameter->enum;
        }
        if ( $parameter->required ) {
          $required[] = $parameter->name;
        }
      }
      // Gemini requires properties to be an object (stdClass), not an array
      $json['parameters']['properties'] = empty( $properties ) ? new stdClass() : (object) $properties;
      $json['parameters']['required'] = $required;
    }

    return $json;
  }

  public static function fromJson( array $json ): Meow_MWAI_Query_Function {
    $funcName = $json['name'];
    $funcDesc = $json['description'] ?? '';
    $funcType = $json['type'] ?? null;
    $funcId = $json['id'] ?? null;
    $funcTarget = $json['target'] ?? null;
    $funcBehavior = $json['behavior'] ?? null;
    if ( $funcId === null && !empty( $json['snippetId'] ) ) {
      $funcId = $json['snippetId'];
    }
    $args = [];
    if ( !empty( $json['args'] ) ) {
      foreach ( $json['args'] as $arg ) {
        $name = ltrim( $arg['name'], '$' );
        $desc = $arg['description'] ?? null;
        $type = $arg['type'] ?? 'string';
        $required = $arg['required'] ?? false;
        $args[] = new Meow_MWAI_Query_Parameter( $name, $desc, $type, $required );
      }
    }
    return new self( $funcName, $funcDesc, $args, $funcType, $funcId, $funcTarget, $funcBehavior );
  }

  public static function toJson( Meow_MWAI_Query_Function $function ): array {
    $json = [
      'name' => $function->name,
      'desc' => $function->description,
      'type' => $function->type,
      'id' => $function->id,
      'target' => $function->target,
      'behavior' => $function->behavior,
      'args' => [],
    ];

    foreach ( $function->parameters as $param ) {
      $json['args'][] = [
        'name' => $param->name,
        'desc' => $param->description,
        'type' => $param->type,
        'required' => $param->required,
      ];
    }

    return $json;
  }
}
