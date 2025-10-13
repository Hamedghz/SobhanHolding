from pathlib import Path

SPEC_PATH = Path(__file__).resolve().parents[1] / "ANDROID_APP_SPEC.md"


def test_spec_file_exists():
    assert SPEC_PATH.exists(), "Specification file is missing"


def test_spec_contains_required_sections():
    text = SPEC_PATH.read_text(encoding="utf-8")
    required_sections = [
        "Overview",
        "Application Architecture",
        "Data Models",
        "Workflows",
        "Error Handling",
        "Offline Strategy",
        "UI/UX Guidelines",
        "Testing Strategy",
        "Deployment & Distribution",
        "Future Enhancements",
    ]
    for section in required_sections:
        assert f"##" in text, "Specification appears to be empty"
        assert section in text, f"Missing section: {section}"


def test_spec_mentions_glassmorphism_and_room():
    text = SPEC_PATH.read_text(encoding="utf-8").lower()
    assert "glassmorphism" in text, "Expected glassmorphism details"
    assert "room" in text, "Expected Room database references"
