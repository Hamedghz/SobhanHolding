"""Utility script to package the legacy gallery module into a zip archive."""

from __future__ import annotations

import argparse
import shutil
from pathlib import Path


def create_archive(output: Path, source_dir: Path) -> Path:
    if not source_dir.is_dir():
        raise SystemExit(f"Source directory '{source_dir}' does not exist or is not a directory.")

    output = output.with_suffix(".zip")
    output.parent.mkdir(parents=True, exist_ok=True)

    if output.exists():
        output.unlink()

    base_name = output.with_suffix("")
    shutil.make_archive(str(base_name), "zip", root_dir=source_dir.parent, base_dir=source_dir.name)
    return output


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Create a distributable zip archive of the legacy gallery module.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("legacy_gallery_template"),
        help="Destination path for the generated zip archive (default: legacy_gallery_template.zip).",
    )
    parser.add_argument(
        "--source",
        type=Path,
        default=Path("legacygallery"),
        help="Source project directory to package (default: legacygallery).",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    archive_path = create_archive(args.output, args.source)
    print(f"Created template archive at {archive_path.resolve()}")


if __name__ == "__main__":
    main()
