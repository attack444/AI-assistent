<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly
?>

<div class="section ai-buddy-accordion-section">
    <div class="section-title active"><?php 
echo esc_html__( 'Model Options', 'aibuddy-openai-chatgpt' );
?></div>
    <div class="section-content content-params">
        <div class="section-subtitle"><span><?php 
echo esc_html__( 'AI Model', 'aibuddy-openai-chatgpt' );
?>:</span></div>
        <select name="ai-buddy-ai-model" id="language-model-select"  class="ai-buddy-select">
            <?php 
require AI_BUDDY_PATH . '/includes/admin/model-list.php';
?>
        </select>
        <div class="section-button-box section-button-box-full">
            <button type="button" class="ai-buddy-button gray button-model-params"><?php 
echo esc_html__( 'Model options', 'aibuddy-openai-chatgpt' );
?></button>
            <button class="ai-buddy-button gray button-model-prompts right-alignment"><?php 
echo esc_html__( 'Promps', 'aibuddy-openai-chatgpt' );
?></button>
        </div>
    </div>
</div>

<div class="section ai-buddy-accordion-section">
	<div class="section-title"><?php 
echo esc_html__( 'Options of content', 'aibuddy-openai-chatgpt' );
?></div>
	<div class="section-content content-params">
		<div class="section-field">
			<div class="check-box">
				<input type="checkbox" class="checkbox" id="check-bulk-content" />
				<label for="check-bulk-content"></label>
				<span class="checkbox-text"><?php 
echo esc_html__( 'Bulk content builder', 'aibuddy-openai-chatgpt' );
?></span>
			</div>
		</div>
		<div class="section-subtitle"><span><?php 
echo esc_html__( 'Language of the text', 'aibuddy-openai-chatgpt' );
?>:</span></div>
		<select id="select-language" class="ai-buddy-select">
			<option value="Arabic"><?php 
echo esc_html__( 'Arabic', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Bulgarian"><?php 
echo esc_html__( 'Bulgarian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Chinese"><?php 
echo esc_html__( 'Chinese', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Croatian"><?php 
echo esc_html__( 'Croatian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Czech"><?php 
echo esc_html__( 'Czech', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Danish"><?php 
echo esc_html__( 'Danish', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Dutch"><?php 
echo esc_html__( 'Dutch', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="English" selected><?php 
echo esc_html__( 'English', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Estonian"><?php 
echo esc_html__( 'Estonian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Filipino"><?php 
echo esc_html__( 'Filipino', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Finnish"><?php 
echo esc_html__( 'Finnish', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="French"><?php 
echo esc_html__( 'French', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="German"><?php 
echo esc_html__( 'German', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Greek"><?php 
echo esc_html__( 'Greek', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Hebrew"><?php 
echo esc_html__( 'Hebrew', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Hindi"><?php 
echo esc_html__( 'Hindi', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Hungarian"><?php 
echo esc_html__( 'Hungarian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Indonesian"><?php 
echo esc_html__( 'Indonesian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Italian"><?php 
echo esc_html__( 'Italian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Indonesian"><?php 
echo esc_html__( 'Indonesian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Japanese"><?php 
echo esc_html__( 'Japanese', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Korean"><?php 
echo esc_html__( 'Korean', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Latvian"><?php 
echo esc_html__( 'Latvian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Lithuanian"><?php 
echo esc_html__( 'Lithuanian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Malay"><?php 
echo esc_html__( 'Malay', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Norwegian"><?php 
echo esc_html__( 'Norwegian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Polish"><?php 
echo esc_html__( 'Polish', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Portuguese"><?php 
echo esc_html__( 'Portuguese', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Romanian"><?php 
echo esc_html__( 'Romanian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Russian"><?php 
echo esc_html__( 'Russian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Serbian"><?php 
echo esc_html__( 'Serbian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Slovak"><?php 
echo esc_html__( 'Slovak', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Serbian"><?php 
echo esc_html__( 'Serbian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Slovenian"><?php 
echo esc_html__( 'Slovenian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Spanish"><?php 
echo esc_html__( 'Spanish', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Swedish"><?php 
echo esc_html__( 'Swedish', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Thai"><?php 
echo esc_html__( 'Thai', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Turkish"><?php 
echo esc_html__( 'Turkish', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Ukrainian"><?php 
echo esc_html__( 'Ukrainian', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Vietnamese"><?php 
echo esc_html__( 'Vietnamese', 'aibuddy-openai-chatgpt' );
?></option>
		</select>
		<div class="section-subtitle"><span><?php 
echo esc_html__( 'Writing style', 'aibuddy-openai-chatgpt' );
?>:</span></div>
		<select id="select-style" class="ai-buddy-select">
			<option value="Descriptive" selected><?php 
echo esc_html__( 'Descriptive', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Informative"><?php 
echo esc_html__( 'Informative', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Creative"><?php 
echo esc_html__( 'Creative', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Narrative"><?php 
echo esc_html__( 'Narrative', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Persuasive"><?php 
echo esc_html__( 'Persuasive', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Reflective"><?php 
echo esc_html__( 'Reflective', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Argumentative"><?php 
echo esc_html__( 'Argumentative', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Analytical"><?php 
echo esc_html__( 'Analytical', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Journalistic"><?php 
echo esc_html__( 'Journalistic', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Technical"><?php 
echo esc_html__( 'Technical', 'aibuddy-openai-chatgpt' );
?></option>
		</select>
		<div class="section-subtitle"><span><?php 
echo esc_html__( 'Writing tone', 'aibuddy-openai-chatgpt' );
?>:</span></div>
		<select id="select-tone" class="ai-buddy-select">
			<option value="Neutral" selected><?php 
echo esc_html__( 'Neutral', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Formal"><?php 
echo esc_html__( 'Formal', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Assertive"><?php 
echo esc_html__( 'Assertive', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Cheerful"><?php 
echo esc_html__( 'Cheerful', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Humorous"><?php 
echo esc_html__( 'Humorous', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Informal"><?php 
echo esc_html__( 'Informal', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Inspirational"><?php 
echo esc_html__( 'Inspirational', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Professional"><?php 
echo esc_html__( 'Professional', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Coincidental"><?php 
echo esc_html__( 'Coincidental', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Emotional"><?php 
echo esc_html__( 'Emotional', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Persuasive"><?php 
echo esc_html__( 'Persuasive', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Supportive"><?php 
echo esc_html__( 'Supportive', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Sarcastic"><?php 
echo esc_html__( 'Sarcastic', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Condescending"><?php 
echo esc_html__( 'Condescending', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Skeptical"><?php 
echo esc_html__( 'Skeptical', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Narrative"><?php 
echo esc_html__( 'Narrative', 'aibuddy-openai-chatgpt' );
?></option>
			<option value="Journalistic"><?php 
echo esc_html__( 'Journalistic', 'aibuddy-openai-chatgpt' );
?></option>
		</select>
	</div>
</div>

<div class="section ai-buddy-accordion-section ai-buddy-seo-details">
    <div class="section-title"><?php 
echo esc_html__( 'SEO Details', 'aibuddy-openai-chatgpt' );
?> <var><?php 
echo esc_html__( 'NEW', 'aibuddy-openai-chatgpt' );
?></var></div>
    <div class="section-content content-params">
        <div class="section-subtitle">
            <span><?php 
echo esc_html__( 'Keywords:', 'aibuddy-openai-chatgpt' );
?></span>
            <label for="ai-buddy-content-keywords"></label>
            <input type="text" placeholder="istanbul, iconic foods, things to do" id="ai-buddy-content-keywords">
        </div>

        <div class="section-field-information">
            <span class="aibuddy-information"></span><?php 
echo esc_html__( 'Separate with commas.', 'aibuddy-openai-chatgpt' );
?>
        </div>

        <div class="section-subtitle">
            <div class="check-box">
                <?php 
echo esc_html__( 'Make keywords bold', 'aibuddy-openai-chatgpt' );
?>
                <input type="checkbox" class="checkbox" id="ai-buddy-make-keywords-bold" />
                <label for="ai-buddy-make-keywords-bold"></label>
            </div>
        </div>
        <div class="section-field-information">
            <span class="aibuddy-information"></span><?php 
echo esc_html__( 'Marking all the keywords bold is a recommended SEO strategy.', 'aibuddy-openai-chatgpt' );
?>
        </div>

        <?php 
?>
                <div class="section-subtitle">
                    <div class="check-box">
                        <?php 
echo esc_html__( 'Yoast Integration', 'aibuddy-openai-chatgpt' );
?>
                        <var><?php 
echo esc_html__( 'PRO', 'aibuddy-openai-chatgpt' );
?></var>
    
                        <div class="ai-buddy-blocked-input">
                            <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=ai_buddy_content_builder-pricing' ) );
//phpcs:ignore
?>" target="_blank"></a>
                            <input type="checkbox" class="checkbox" id="ai-buddy-yoast-integration" disabled readonly />
                            <label for="ai-buddy-yoast-integration"></label>
                        </div>
                    </div>
                </div>
                <div class="section-field-information">
                    <span class="aibuddy-information"></span><?php 
echo esc_html__( 'This feature only available in Premium plans. ', 'aibuddy-openai-chatgpt' );
echo esc_html__( 'Generate Yoast SEO meta description.', 'aibuddy-openai-chatgpt' );
?>
                </div>
        <?php 
?>
            <div class="section-subtitle">
                <div class="check-box">
                    <?php 
echo esc_html__( 'Rank Math Integration', 'aibuddy-openai-chatgpt' );
?>
                    <var><?php 
echo esc_html__( 'PRO', 'aibuddy-openai-chatgpt' );
?></var>

                    <div class="ai-buddy-blocked-input">
                        <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=ai_buddy_content_builder-pricing' ) );
//phpcs:ignore
?>" target="_blank"></a>
                        <input type="checkbox" class="checkbox" id="ai-buddy-rank-math-integration" disabled readonly />
                        <label for="ai-buddy-rank-math-integration"></label>
                    </div>
                </div>
            </div>
            <div class="section-field-information">
                <span class="aibuddy-information"></span><?php 
echo esc_html__( 'This feature only available in Premium plans. ', 'aibuddy-openai-chatgpt' );
echo esc_html__( 'Generate Rank Math meta description.', 'aibuddy-openai-chatgpt' );
?>
            </div>
        <?php 
?>
        
    </div>
</div>
