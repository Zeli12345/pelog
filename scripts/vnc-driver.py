#!/usr/bin/env python3
"""PELOG VNC driver â€” otomasi VM untuk pengujian Fase 6.

Contoh:
  python scripts/vnc-driver.py "key:win-r" "type:cmd" "key:enter" "sleep:2" "capture:out.png"

Aksi yang didukung:
  key:NAMA        -> tekan tombol / kombinasi (mis. key:enter, key:ctrl-c, key:win-r)
  type:TEKS       -> ketik teks
  click:X,Y       -> klik kiri pada koordinat guest
  move:X,Y        -> pindahkan mouse guest
  capture:PATH    -> simpan screenshot guest
  sleep:SEC       -> tunggu
"""

import argparse
import sys
import time

from vncdotool import api

SERVER = "127.0.0.1::5900"
PASSWORD = "pelog"

KEY_ALIASES = {
    "win": "super",
    "windows": "super",
    "meta": "super",
}

SHIFT_MAP = {
    "~": "`", "!": "1", "@": "2", "#": "3", "$": "4", "%": "5",
    "^": "6", "&": "7", "*": "8", "(": "9", ")": "0",
    "_": "-", "+": "=", "{": "[", "}": "]", "|": "\\",
    ":": ";", '"': "'", "<": ",", ">": ".", "?": "/",
}


def type_text(client, text: str) -> None:
    for char in text:
        if char == " ":
            client.keyPress("space")
        elif char.isalpha() and char.isupper():
            client.keyDown("shift")
            client.keyPress(char.lower())
            client.keyUp("shift")
        elif char in SHIFT_MAP:
            client.keyDown("shift")
            client.keyPress(SHIFT_MAP[char])
            client.keyUp("shift")
        else:
            client.keyPress(char.lower() if char.isalpha() else char)


def press_key(client, name: str) -> None:
    name = name.lower()
    parts = [KEY_ALIASES.get(part, part) for part in name.split("-")]
    final = parts[-1]
    modifiers = parts[:-1]

    for modifier in modifiers:
        client.keyDown(modifier)

    client.keyPress(final)

    for modifier in reversed(modifiers):
        client.keyUp(modifier)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("actions", nargs="+")
    args = parser.parse_args()

    client = api.connect(SERVER, password=PASSWORD)
    client.timeout = 30

    try:
        for action in args.actions:
            kind, _, value = action.partition(":")

            if kind == "key":
                press_key(client, value)
            elif kind == "combo":
                keys = [KEY_ALIASES.get(part, part) for part in value.split("+")]
                for modifier in keys[:-1]:
                    client.keyDown(modifier)
                    client.pause(0.12)
                client.keyPress(keys[-1])
                client.pause(0.12)
                for modifier in reversed(keys[:-1]):
                    client.keyUp(modifier)
                    client.pause(0.12)
            elif kind == "type":
                type_text(client, value)
            elif kind == "click":
                x, y = value.split(",")
                client.mouseMove(int(x), int(y))
                client.mousePress(1)
            elif kind == "move":
                x, y = value.split(",")
                client.mouseMove(int(x), int(y))
            elif kind == "capture":
                client.captureScreen(value)
            elif kind == "sleep":
                time.sleep(float(value))
            elif kind == "size":
                client.refreshScreen()
                width, height = client.screen.size
                print(f"screen: {width}x{height}")
            else:
                print(f"Aksi tidak dikenal: {action}", file=sys.stderr)
                return 2

            client.pause(0.3)
    finally:
        client.disconnect()

    return 0


if __name__ == "__main__":
    sys.exit(main())
