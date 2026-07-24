#!/usr/bin/env python3
"""Build the .pot template and per-locale .po/.mo files for the plugin.

Reads .i18n-tools/strings.json (produced by extract_strings.py) plus the
translation dictionaries under .i18n-tools/translations/, validates 100%
coverage, then writes everything WordPress expects into languages/.
"""
import os
import sys
import json
import struct
import array
import importlib.util
import datetime

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TOOLS_DIR = os.path.dirname(os.path.abspath(__file__))
LANG_DIR = os.path.join(ROOT, 'languages')
DOMAIN = 'garion-projetos-site-health-monitor'
PROJECT_NAME = 'Garion Projetos Site Health Monitor'

LOCALES = ['pt_BR', 'es_ES', 'ru_RU', 'zh_CN']

os.makedirs(LANG_DIR, exist_ok=True)

with open(os.path.join(TOOLS_DIR, 'strings.json'), encoding='utf-8') as f:
    STRINGS = json.load(f)


def load_translation_module(locale):
    path = os.path.join(TOOLS_DIR, 'translations', locale + '.py')
    spec = importlib.util.spec_from_file_location('translations_' + locale, path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def escape_po_string(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')


def po_header(locale, plural_forms):
    now = datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M+0000')
    return (
        '# Translation of ' + PROJECT_NAME + ' in ' + locale + '\n'
        '# This file is distributed under the same license as the ' + PROJECT_NAME + ' package.\n'
        'msgid ""\n'
        'msgstr ""\n'
        '"Project-Id-Version: ' + PROJECT_NAME + '\\n"\n'
        '"Report-Msgid-Bugs-To: \\n"\n'
        '"POT-Creation-Date: ' + now + '\\n"\n'
        '"PO-Revision-Date: ' + now + '\\n"\n'
        '"Language-Team: \\n"\n'
        '"MIME-Version: 1.0\\n"\n'
        '"Content-Type: text/plain; charset=UTF-8\\n"\n'
        '"Content-Transfer-Encoding: 8bit\\n"\n'
        '"Language: ' + locale + '\\n"\n'
        '"Plural-Forms: ' + plural_forms + '\\n"\n'
        '"X-Domain: ' + DOMAIN + '\\n"\n'
        '\n'
    )


def build_pot():
    lines = []
    now = datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M+0000')
    lines.append('# Copyright (C) Garion Projetos\n')
    lines.append('# This file is distributed under the same license as the ' + PROJECT_NAME + ' package.\n')
    lines.append('msgid ""\n')
    lines.append('msgstr ""\n')
    lines.append('"Project-Id-Version: ' + PROJECT_NAME + '\\n"\n')
    lines.append('"Report-Msgid-Bugs-To: \\n"\n')
    lines.append('"POT-Creation-Date: ' + now + '\\n"\n')
    lines.append('"MIME-Version: 1.0\\n"\n')
    lines.append('"Content-Type: text/plain; charset=UTF-8\\n"\n')
    lines.append('"Content-Transfer-Encoding: 8bit\\n"\n')
    lines.append('"PO-Revision-Date: ' + now + '\\n"\n')
    lines.append('"Language-Team: LANGUAGE <LL@li.org>\\n"\n')
    lines.append('"X-Domain: ' + DOMAIN + '\\n"\n')
    lines.append('\n')
    for entry in STRINGS:
        for loc in entry['locations']:
            lines.append('#: ' + loc + '\n')
        if entry['context']:
            lines.append('msgctxt "' + escape_po_string(entry['context']) + '"\n')
        lines.append('msgid "' + escape_po_string(entry['msgid']) + '"\n')
        if entry['msgid_plural']:
            lines.append('msgid_plural "' + escape_po_string(entry['msgid_plural']) + '"\n')
            lines.append('msgstr[0] ""\n')
            lines.append('msgstr[1] ""\n')
        else:
            lines.append('msgstr ""\n')
        lines.append('\n')
    return ''.join(lines)


def build_po(locale, entries, plurals, plural_forms):
    missing = []
    lines = [po_header(locale, plural_forms)]
    for entry in STRINGS:
        msgid = entry['msgid']
        for loc in entry['locations']:
            lines.append('#: ' + loc + '\n')
        if entry['context']:
            lines.append('msgctxt "' + escape_po_string(entry['context']) + '"\n')
        lines.append('msgid "' + escape_po_string(msgid) + '"\n')
        if entry['msgid_plural']:
            plural_entry = plurals.get(msgid)
            if not plural_entry or plural_entry['msgid_plural'] != entry['msgid_plural']:
                missing.append(msgid)
                forms = ['']
            else:
                forms = plural_entry['forms']
            lines.append('msgid_plural "' + escape_po_string(entry['msgid_plural']) + '"\n')
            for i, form in enumerate(forms):
                lines.append('msgstr[' + str(i) + '] "' + escape_po_string(form) + '"\n')
        else:
            translation = entries.get(msgid)
            if translation is None:
                missing.append(msgid)
                translation = ''
            lines.append('msgstr "' + escape_po_string(translation) + '"\n')
        lines.append('\n')
    return ''.join(lines), missing


def generate_mo(messages):
    """messages: {msgid_bytes: msgstr_bytes}. Follows the reference algorithm
    from CPython's Tools/i18n/msgfmt.py so the binary layout is standards-compliant."""
    keys = sorted(messages.keys())
    offsets = []
    ids = b''
    strs = b''
    for key in keys:
        value = messages[key]
        offsets.append((len(ids), len(key), len(strs), len(value)))
        ids += key + b'\x00'
        strs += value + b'\x00'

    keystart = 7 * 4 + 16 * len(keys)
    valuestart = keystart + len(ids)
    koffsets = []
    voffsets = []
    for o1, l1, o2, l2 in offsets:
        koffsets += [l1, o1 + keystart]
        voffsets += [l2, o2 + valuestart]
    offsets_flat = koffsets + voffsets

    output = struct.pack(
        'Iiiiiii',
        0x950412de,          # magic
        0,                    # version
        len(keys),             # number of entries
        7 * 4,                 # start of key index
        7 * 4 + len(keys) * 8,  # start of value index
        0, 0,                   # size and offset of hash table (unused)
    )
    output += array.array('i', offsets_flat).tobytes()
    output += ids
    output += strs
    return output


def write_mo(path, entries, plurals, plural_forms):
    messages = {}
    meta = (
        'Content-Type: text/plain; charset=UTF-8\n'
        'Plural-Forms: ' + plural_forms + '\n'
    )
    messages[b''] = meta.encode('utf-8')

    for entry in STRINGS:
        msgid = entry['msgid']
        if entry['msgid_plural']:
            plural_entry = plurals.get(msgid)
            forms = plural_entry['forms'] if plural_entry else ['']
            key = (msgid + '\x00' + entry['msgid_plural']).encode('utf-8')
            value = '\x00'.join(forms).encode('utf-8')
            messages[key] = value
        else:
            translation = entries.get(msgid, '')
            if translation:
                messages[msgid.encode('utf-8')] = translation.encode('utf-8')

    data = generate_mo(messages)
    with open(path, 'wb') as f:
        f.write(data)


def main():
    pot = build_pot()
    pot_path = os.path.join(LANG_DIR, DOMAIN + '.pot')
    with open(pot_path, 'w', encoding='utf-8', newline='\n') as f:
        f.write(pot)
    print('Wrote', pot_path)

    all_missing = {}
    for locale in LOCALES:
        mod = load_translation_module(locale)
        po_text, missing = build_po(locale, mod.ENTRIES, mod.PLURALS, mod.PLURAL_FORMS)
        if missing:
            all_missing[locale] = missing
        po_path = os.path.join(LANG_DIR, DOMAIN + '-' + locale + '.po')
        with open(po_path, 'w', encoding='utf-8', newline='\n') as f:
            f.write(po_text)
        mo_path = os.path.join(LANG_DIR, DOMAIN + '-' + locale + '.mo')
        write_mo(mo_path, mod.ENTRIES, mod.PLURALS, mod.PLURAL_FORMS)
        print('Wrote', po_path, 'and', mo_path)

    if all_missing:
        print('\n MISSING TRANSLATIONS:')
        for locale, msgids in all_missing.items():
            print(f'  {locale}: {len(msgids)} missing')
            for m in msgids[:20]:
                print('    -', repr(m))
        sys.exit(1)
    else:
        print('\nAll locales have 100% coverage of', len(STRINGS), 'strings.')


if __name__ == '__main__':
    main()
