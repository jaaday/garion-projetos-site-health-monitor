#!/usr/bin/env python3
"""Extract WordPress i18n strings for text domain 'garion-projetos-technical-seo-toolkit'.

Scans every .php file in the plugin (excluding tests/docs) and pulls out the
msgid (and msgid_plural, for _n/_nx) from calls to the standard WP i18n
functions, recording the file:line of each occurrence for the POT file.
"""
import os
import re
import json

DOMAIN = "garion-projetos-site-health-monitor"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Matches a PHP single or double quoted string literal, no interpolation handling needed
# since these are translator-facing literals (WP.org guidelines require literals anyway).
STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""

FUNCS = {
    '__': ('single', 1),
    '_e': ('single', 1),
    'esc_html__': ('single', 1),
    'esc_html_e': ('single', 1),
    'esc_attr__': ('single', 1),
    'esc_attr_e': ('single', 1),
    '_x': ('single_context', 1),
    'esc_html_x': ('single_context', 1),
    'esc_attr_x': ('single_context', 1),
    '_n': ('plural', 2),
    '_nx': ('plural_context', 2),
}

def unescape(s):
    return s.replace("\\'", "'").replace('\\"', '"').replace('\\\\', '\\')

def find_calls(text):
    results = []
    for fname in FUNCS:
        pattern = re.compile(r'\b' + re.escape(fname) + r'\s*\(')
        for m in pattern.finditer(text):
            start = m.end()
            # naive arg parser respecting quotes and parens
            depth = 1
            i = start
            args = []
            cur = ''
            in_str = False
            str_ch = ''
            while i < len(text) and depth > 0:
                ch = text[i]
                if in_str:
                    cur += ch
                    if ch == '\\':
                        i += 1
                        if i < len(text):
                            cur += text[i]
                    elif ch == str_ch:
                        in_str = False
                elif ch in ('"', "'"):
                    in_str = True
                    str_ch = ch
                    cur += ch
                elif ch == '(':
                    depth += 1
                    cur += ch
                elif ch == ')':
                    depth -= 1
                    if depth > 0:
                        cur += ch
                elif ch == ',' and depth == 1:
                    args.append(cur)
                    cur = ''
                else:
                    cur += ch
                i += 1
            if cur.strip():
                args.append(cur)
            args = [a.strip() for a in args]
            line_no = text.count('\n', 0, m.start()) + 1
            results.append((fname, args, line_no))
    return results

def parse_str_literal(arg):
    m = re.match(r'^' + STR + r'$', arg.strip())
    if not m:
        return None
    return unescape(m.group(1) if m.group(1) is not None else m.group(2))

entries = {}  # key: (msgid, msgid_plural or None, context or None) -> list of (file, line)

for dirpath, dirnames, filenames in os.walk(ROOT):
    dirnames[:] = [d for d in dirnames if d not in ('tests', 'docs', '.git', '.i18n-tools', 'languages')]
    for fn in filenames:
        if not fn.endswith('.php'):
            continue
        path = os.path.join(dirpath, fn)
        with open(path, encoding='utf-8') as f:
            text = f.read()
        rel = os.path.relpath(path, ROOT).replace('\\', '/')
        for fname, args, line_no in find_calls(text):
            kind, domain_pos = FUNCS[fname]
            # domain is always the LAST arg for these functions
            if not args:
                continue
            domain_arg = parse_str_literal(args[-1])
            if domain_arg != DOMAIN:
                continue
            if kind == 'single':
                msgid = parse_str_literal(args[0])
                if msgid is None:
                    continue
                key = (msgid, None, None)
            elif kind == 'single_context':
                msgid = parse_str_literal(args[0])
                context = parse_str_literal(args[1]) if len(args) > 1 else None
                if msgid is None:
                    continue
                key = (msgid, None, context)
            elif kind == 'plural':
                msgid = parse_str_literal(args[0])
                msgid_plural = parse_str_literal(args[1]) if len(args) > 1 else None
                if msgid is None:
                    continue
                key = (msgid, msgid_plural, None)
            elif kind == 'plural_context':
                msgid = parse_str_literal(args[0])
                msgid_plural = parse_str_literal(args[1]) if len(args) > 1 else None
                context = parse_str_literal(args[3]) if len(args) > 3 else None
                if msgid is None:
                    continue
                key = (msgid, msgid_plural, context)
            else:
                continue
            entries.setdefault(key, []).append(f"{rel}:{line_no}")

out = []
for (msgid, msgid_plural, context), locs in sorted(entries.items(), key=lambda kv: kv[1][0]):
    out.append({
        'msgid': msgid,
        'msgid_plural': msgid_plural,
        'context': context,
        'locations': locs,
    })

out_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'strings.json')
with open(out_path, 'w', encoding='utf-8') as f:
    json.dump(out, f, ensure_ascii=False, indent=2)
print(f"Wrote {len(out)} unique strings to {out_path}")
