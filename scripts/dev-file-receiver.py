#!/usr/bin/env python3
"""Receiver file sederhana untuk mengambil berkas dari VM (Fase 6 debugging).

Jalankan di host:
  python scripts/dev-file-receiver.py

Di VM:
  curl -X POST --data-binary @C:\\path\\file http://192.168.0.100:8091/namafile

Berkas tersimpan di E:\\pelog-build\\received\\<namafile>
"""

import http.server
import os

OUTPUT_DIR = r"E:\pelog-build\received"


class Handler(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        name = os.path.basename(self.path.lstrip("/")) or "upload.bin"
        length = int(self.headers.get("Content-Length", 0))
        data = self.rfile.read(length)

        os.makedirs(OUTPUT_DIR, exist_ok=True)
        path = os.path.join(OUTPUT_DIR, name)

        with open(path, "wb") as handle:
            handle.write(data)

        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.end_headers()
        self.wfile.write(b"ok")

        print(f"received: {name} ({len(data)} bytes)")

    def log_message(self, *args):
        pass


if __name__ == "__main__":
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    server = http.server.HTTPServer(("0.0.0.0", 8091), Handler)
    print("file receiver on :8091 -> E:\\pelog-build\\received")
    server.serve_forever()
