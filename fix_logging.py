#!/usr/bin/env python3

with open('bi_processor.py', 'r') as f:
    content = f.read()

# Replace the logging file handler path
content = content.replace(
    "logging.FileHandler('bi_refresh.log')",
    "logging.FileHandler('/app/logs/bi_refresh.log')"
)

with open('bi_processor.py', 'w') as f:
    f.write(content)

print("Updated bi_processor.py logging path")
