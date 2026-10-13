#!/usr/bin/env python3
"""Fail when German text appears outside translation files.

Code, comments, tests, scripts and docs are English only; German belongs in
lang/de and the German language pack. Run from the repository root:

    python3 bin/check-german.py

A line is reported when it contains an umlaut or sharp s, a typical German
word spelled with ae/oe/ue, an unambiguous German word (siehe, nicht, oder …)
or at least two common German words. Lines that
must stay German (for example a regex matching German input) end with the
marker `german-ok`. A test file whose fixtures are German on purpose mentions
`german-fixtures` once: its string literals are skipped, its comments are not.
Whole paths go into EXCLUDE, proper nouns into PROPER_NOUNS below.
"""
import re
import subprocess
import sys

EXCLUDE = re.compile(
    r'(^|/)lang/de(/|\.json$)'
    r'|^plugins/language-de/(lang|demo)/'
    r'|^(vendor|node_modules|sbom|public/build)/'
    r'|^bin/check-german\.py$'
    r'|\.(png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|zip|lock|sql|pdf|mp4|webm)$'
    r'|(^|/)package-lock\.json$'
    r'|^public/css/bootstrap-icons\.css$'
)

GERMAN_WORDS = (
    'und nicht wird werden wurde wuerde beim oder einen einer eines einem auch wenn keine keinen kein sonst noch '
    'zum zur dem sich ohne bleibt bleiben gibt statt schon danach davor immer hier jeder jede jedes jeden Seite '
    'Seiten Datei Dateien Sprache Sprachen Einstellung Einstellungen Inhalt Inhalte gespeichert Hinweis nach aus '
    'bei ist sind kommt muss kann soll damit weil dass Beitrag Feld Felder Bilder Nutzer Benutzer speichern '
    'anzeigen zeigt fehlt fehlen leer selbst etwa daher dann denn doch eigene eigenen ganze gleich mehr neue '
    'neuen nie oben unten richtig Stand Vorschau erst zuerst wieder Wert Werte Fehler Zeile Zeilen Ordner Pfad '
    'Passwort Anmeldung Rechte Zeichen Liste Kopie alten Abschnitt Beispiel sowie bzw ggf liefert liest schreibt '
    'setzt erlaubt Ziel Quelle Wenn Nur Bei Der Das Ein Eine Kein Keine Ohne Nach Auch Damit Neue Hier Sonst Wird '
    # function words: nearly every German sentence has two of them, English ones practically never
    'der den des ein eine mit von auf zu im um wie ihm ihn ihr wir sie vom bis als da sondern jetzt hat haben '
    'gesperrt haengt geht steht liegt laeuft macht passt fehlt nimmt braucht Sie Wir Es Im Zu Von Mit Auf Als Wie'
).split()
TRANSLITERATED = (
    'fuer ueber zurueck waere koennen koennte muessen muesste loeschen geloescht Aenderung Aenderungen '
    'Uebersetzung Uebersetzungen Staende Bloecke Beitraege prueft pruefen geprueft spaeter frueher waehrend '
    'gueltig ungueltig moeglich noetig hoechstens Groesse Schluessel Eintraege '
    # unambiguous on their own
    'Siehe siehe nicht oder wird werden wurde sonst bzw ggf usw Achtung Hinweis Beispiel Ueberschrift'
).split()

# Names, not German text (the demo address)
PROPER_NOUNS = re.compile(r'Friedrichstraße|Friedrichstra\\u00dfe')
STRING_LITERAL = re.compile(r"'(?:[^'\\]|\\.)*'|\"(?:[^\"\\]|\\.)*\"")

WORD_RE = re.compile(r'(?<![\w$\-./\'"])(' + '|'.join(GERMAN_WORDS) + r')(?![\w\-(/\'"])')
TRANSLIT_RE = re.compile(r'\b(' + '|'.join(TRANSLITERATED) + r')\b')
UMLAUT_RE = re.compile(r'[äöüÄÖÜß]')


def offending(line: str, fixtures: bool) -> bool:
    if 'german-ok' in line:
        return False
    line = PROPER_NOUNS.sub('', line)
    if fixtures:
        line = STRING_LITERAL.sub("''", line)
    if UMLAUT_RE.search(line) or TRANSLIT_RE.search(line):
        return True
    return len(set(WORD_RE.findall(line))) >= 2


def main() -> int:
    files = subprocess.run(['git', 'ls-files'], capture_output=True, text=True, check=True).stdout.splitlines()
    problems = []
    for path in files:
        if EXCLUDE.search(path):
            continue
        try:
            with open(path, encoding='utf-8') as handle:
                text = handle.read()
        except (UnicodeDecodeError, IsADirectoryError, FileNotFoundError):
            continue
        fixtures = 'german-fixtures' in text
        for number, line in enumerate(text.split('\n'), 1):
            if offending(line, fixtures):
                problems.append(f'{path}:{number}: {line.strip()[:160]}')

    if problems:
        print('German text found (code, comments and docs are English only; German goes into lang/de):')
        print('\n'.join(problems))
        print(f'\n{len(problems)} line(s). Translate them, or end a line that must stay German with "german-ok".')
        return 1

    print('No German text outside translation files.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
