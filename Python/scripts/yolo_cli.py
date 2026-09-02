"""Reliable Windows entry point for the installed Ultralytics CLI.

This wrapper deliberately uses the current venv Python instead of relying on
the generated ``yolo.exe`` launcher or a global PATH entry.
"""
from ultralytics.cfg import entrypoint


if __name__ == "__main__":
    entrypoint()
