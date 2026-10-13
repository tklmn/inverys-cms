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

German translation files (lang/de/*.php) hold German text, but their comments
are code like any other: only the comments are checked there (read with PHP's
own tokenizer, so quotes inside the translations cannot confuse it).
"""
import json
import re
import subprocess
import sys

EXCLUDE = re.compile(
    r'(^|/)lang/de\.json$'
    r'|^plugins/language-de/demo/'
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


TRANSLATION_FILE = re.compile(r'(^|/)lang/de/.*\.php$')

# Section headings in translation files are often a single German noun
SECTION_WORDS = re.compile(r'\b(' + '|'.join((
    'Allgemein allgemein Meldungen Erfolgsmeldungen Fehlermeldungen Anbieter Aktionen Felder Feldtypen '
    'Einsendungs Bildverwaltung Abstandsoptionen Benachrichtigungen Papierkorb Benutzerverwaltung '
    'Authentifizierung Berechtigungen Rollen Beschriftungen Beschriftung Gruppen Konto Kategorien Themen '
    'Zahlen Lebenslauf Technologien Projekte Kontakt Kontaktformular Beitragsformular Blocknamen Erweiterungen '
    'Marktplatz Schritt Standard Texte Suchbegriffe Schnellsuche Strg Gemeinsame Bilder Größenoptionen '
    'Seitenverhältnis Optionen Mitglieder Anmeldung Blätterung Feldnamen Validierungsmeldungen Oberfläche '
    'Systemgruppen Rechte Konten Formular Formularzustände Einladen einladen Verwaltung Sektionen'
).split()) + r')\b')


def offending_comment(line: str) -> bool:
    """Stricter for comments in translation files: one German word is enough."""
    if 'german-ok' in line:
        return False
    # Compounds such as "Feld-Einstellungen": every part counts as a word
    words = line.replace('-', ' ')
    return offending(line, False) or bool(SECTION_WORDS.search(words)) or bool(WORD_RE.search(words))

# Prints every comment of a PHP file as "<first line>\t<json text>"
PHP_COMMENTS = (
    'foreach (token_get_all(file_get_contents($argv[1])) as $t) {'
    ' if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {'
    ' echo $t[2], "\\t", json_encode($t[1], JSON_UNESCAPED_UNICODE), "\\n"; } }'
)


def comment_lines(path: str):
    """(line number, text) of every comment line in a PHP file."""
    out = subprocess.run(['php', '-r', PHP_COMMENTS, path], capture_output=True, text=True, check=True).stdout
    for row in out.splitlines():
        start, text = row.split('\t', 1)
        for offset, line in enumerate(json.loads(text).split('\n')):
            yield int(start) + offset, line


def main() -> int:
    files = subprocess.run(['git', 'ls-files'], capture_output=True, text=True, check=True).stdout.splitlines()
    problems = []
    for path in files:
        if EXCLUDE.search(path):
            continue
        if TRANSLATION_FILE.search(path):
            # The translations are German on purpose — their comments are not
            for number, line in comment_lines(path):
                if offending_comment(line):
                    problems.append(f'{path}:{number}: {line.strip()[:160]}')
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
