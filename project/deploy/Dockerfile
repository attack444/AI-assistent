FROM python:3.11-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl default-mysql-client && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

ENV PYTHONUNBUFFERED=1
ENV PYTHONIOENCODING=utf-8
ENV AI_HELPER_API_HOST=0.0.0.0
ENV AI_HELPER_API_PORT=8502
ENV SITES_ROOT=/opt/sites

EXPOSE 8501 8502

# Streamlit (legacy) + API. Next.js panel runs in separate `web` service.
CMD ["sh", "-c", "python api.py & python -m streamlit run app.py --server.port=8501 --server.address=0.0.0.0 --server.headless=true"]
