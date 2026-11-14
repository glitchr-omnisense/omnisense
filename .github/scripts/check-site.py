"""check-site.py <folder> [commit]: a built site checked before it is deployed again (a rollback).

Every file omnibridge-static.json lists is there with its SHA-256, no other file is (but the report and
provenance.json), provenance.json names this report and this folder's checksum, and - given a commit - was
built from that commit. Exit 1 with the problems otherwise.
"""
import hashlib
import json
import os
import sys


def sha(path):
    with open(path, 'rb') as handle:
        return hashlib.sha256(handle.read()).hexdigest()


def main(folder, commit=None):
    report = json.load(open(os.path.join(folder, 'omnibridge-static.json')))
    provenance = json.load(open(os.path.join(folder, 'provenance.json')))
    listed = report['files']
    problems = [f'changed or missing: {f}' for f, e in sorted(listed.items())
                if not os.path.isfile(os.path.join(folder, f)) or sha(os.path.join(folder, f)) != e['sha256']]
    lines = []
    for root, _, files in os.walk(folder):
        for name in files:
            f = os.path.relpath(os.path.join(root, name), folder)
            if f == 'provenance.json':
                continue
            lines.append(f'{f} {sha(os.path.join(folder, f))}')
            if f not in listed and f != 'omnibridge-static.json':
                problems.append(f'not in the report: {f}')
    if provenance['artifact']['report'] != sha(os.path.join(folder, 'omnibridge-static.json')):
        problems.append('omnibridge-static.json is not the one provenance.json names')
    if provenance['artifact']['sha256'] != hashlib.sha256('\n'.join(sorted(lines)).encode()).hexdigest():
        problems.append("the folder's checksum is not the one provenance.json names")
    if commit and provenance['site']['commit'] != commit:
        problems.append(f"built from {provenance['site']['commit']}, not {commit}")
    if problems:
        print('\n'.join(problems[:50]), file=sys.stderr)
        return 1
    print(f"{len(listed)} files checked; the site of commit {provenance['site']['commit']} (committed {provenance['site']['committed']})")
    return 0


if __name__ == '__main__':
    sys.exit(main(*sys.argv[1:3]))
