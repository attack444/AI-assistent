#!/usr/bin/env bash
# Двойной клик или ./START.sh — всё установится и запустится автоматически
cd "$(dirname "$0")"
exec python3 launcher.py
