#!/usr/bin/env python3
"""
Élő nézet a `claude -p --output-format stream-json` folyamához.

A nyers JSON eseményeket olvasható sorokká alakítja, hogy futás közben látszódjon,
mit csinál a fő ügynök ÉS a párhuzamos gorog-elemzo sub-agentek (utóbbihoz a
claude hívásban a --forward-subagent-text kapcsoló kell).

Használat:
    claude -p "..." --output-format stream-json --verbose --forward-subagent-text \
      | python3 bible_import/verse-analysis/stream-view.py
"""
import sys
import json

# Agent tool_use_id -> rövid címke, hogy a sub-agent sorai beazonosíthatók legyenek.
labels: dict[str, str] = {}


def short(text: object, limit: int = 200) -> str:
    collapsed = " ".join(str(text).split())
    return collapsed if len(collapsed) <= limit else collapsed[:limit] + "…"


def main() -> None:
    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            event = json.loads(line)
        except json.JSONDecodeError:
            continue

        etype = event.get("type")
        parent = event.get("parent_tool_use_id")
        indent = "    │ " if parent else ""
        tag = ""
        if parent and parent in labels:
            tag = f"[{labels[parent]}] "

        if etype == "system" and event.get("subtype") == "init":
            print(f"── session start ({event.get('model', '')}) ──", flush=True)

        elif etype == "assistant":
            for chunk in event.get("message", {}).get("content", []):
                ctype = chunk.get("type")
                if ctype == "text":
                    text = chunk.get("text", "").strip()
                    if text:
                        print(f"{indent}💬 {tag}{short(text)}", flush=True)
                elif ctype == "thinking":
                    thinking = chunk.get("thinking", "").strip()
                    if thinking:
                        print(f"{indent}🤔 {tag}{short(thinking, 160)}", flush=True)
                elif ctype == "tool_use":
                    name = chunk.get("name", "?")
                    inp = chunk.get("input", {}) or {}
                    if name in ("Agent", "Task"):
                        desc = inp.get("description") or inp.get("subagent_type") or "agent"
                        labels[chunk.get("id")] = desc
                        print(f"{indent}🚀 {tag}indít ügynök: {desc}", flush=True)
                    else:
                        detail = (
                            inp.get("file_path")
                            or inp.get("command")
                            or inp.get("pattern")
                            or inp.get("description")
                            or ""
                        )
                        print(f"{indent}🔧 {tag}{name} {short(detail, 100)}", flush=True)

        elif etype == "user":
            for chunk in event.get("message", {}).get("content", []):
                if chunk.get("type") == "tool_result" and chunk.get("is_error"):
                    print(f"{indent}⚠️  {tag}tool hiba", flush=True)

        elif etype == "result":
            cost = event.get("total_cost_usd")
            extra = f"  (${cost:.2f})" if isinstance(cost, (int, float)) else ""
            print(f"✅ result: {event.get('subtype', 'done')}{extra}", flush=True)


if __name__ == "__main__":
    try:
        main()
    except (BrokenPipeError, KeyboardInterrupt):
        pass
