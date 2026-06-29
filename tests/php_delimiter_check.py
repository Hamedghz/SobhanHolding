import pathlib
import re
import sys


def balanced(path: pathlib.Path) -> tuple[bool, str]:
    source = path.read_text(encoding="utf-8-sig")
    if "<?" in source:
        source = "\n".join(match.group(1) for match in re.finditer(r"<\?(?:php|=)?(.*?)(?:\?>|$)", source, re.S | re.I))
    stack: list[str] = []
    quote = None
    i = 0
    pairs = {')': '(', ']': '[', '}': '{'}
    while i < len(source):
        char = source[i]
        if quote:
            if char == '\\':
                i += 2
                continue
            if char == quote:
                quote = None
        else:
            if char in "'\"":
                quote = char
            elif source.startswith('//', i) or char == '#':
                end = source.find('\n', i)
                i = len(source) if end < 0 else end
                continue
            elif source.startswith('/*', i):
                end = source.find('*/', i + 2)
                i = len(source) if end < 0 else end + 2
                continue
            elif char in '([{':
                stack.append(char)
            elif char in ')]}':
                if not stack or stack.pop() != pairs[char]:
                    return False, f"delimiter mismatch near offset {i}"
        i += 1
    if quote:
        return False, "unterminated quoted string"
    if stack:
        return False, f"unclosed delimiters: {''.join(stack[-10:])}"
    return True, ""


failures = []
for filename in sys.argv[1:]:
    ok, reason = balanced(pathlib.Path(filename))
    if not ok:
        failures.append(f"{filename}: {reason}")
if failures:
    print('\n'.join(failures))
    raise SystemExit(1)
print(f"PHP delimiter checks passed ({len(sys.argv) - 1} files).")
