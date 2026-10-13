#!/bin/bash
cd "$(dirname "$0")/.."
bad=0
for f in storage/framework/views/*.php; do
  out=$(php -l "$f" 2>&1)
  if ! echo "$out" | grep -q "No syntax errors"; then
    bad=$((bad+1))
    # Determine the source from the comment at the end of the file
    src=$(tail -1 "$f" | grep -oE '/[^ ]*\.blade\.php')
    echo "BROKEN: ${src:-$f}"
    echo "  $(echo "$out" | head -1 | cut -c1-140)"
  fi
done
echo "----"
echo "$bad broken views"
