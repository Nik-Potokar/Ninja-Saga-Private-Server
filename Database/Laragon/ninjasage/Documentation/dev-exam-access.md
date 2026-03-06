# DEV: Exam Button Access via Prestige Levels

**File:** `Panels/HUD.as`
**Status:** Dev only — remove or gate behind an `isDev` flag before production

---

## Overview

At prestige levels 101–104, the corresponding rank exam buttons are made visible in the HUD without any rank requirement check. This allows dev accounts to replay and test each exam independently of the normal progression path.

---

## Level → Exam Mapping

| Level | Exam button shown       | Normal unlock condition          |
|-------|-------------------------|----------------------------------|
| 101   | Chunin Exam             | Level 20 + Rank 1 (Genin)        |
| 102   | Jounin Exam             | Level 40 + Rank 3 (Chunin)       |
| 103   | Special Jounin Exam     | Level 60 + Rank 5 (Jounin)       |
| 104   | Ninja Tutor Exam        | Level 80 + Rank 7 (Special Jounin) |

> **Note:** Level 104 uses `btn_NinjaTutorExam` as a placeholder until a dedicated Kage exam button is added to the HUD.

---

## How It Works

The dev blocks live directly after the normal exam unlock conditions in `HUD()`:

```actionscript
// DEV ONLY: exam replay access at levels 101-104
if(int(Character.character_lvl) == 101)
{
   this.btn_ChuninExam.visible = true;
   this.eventHandler.addListener(this.btn_ChuninExam, MouseEvent.CLICK, this.openExternalPanel, false, 0, true);
}
if(int(Character.character_lvl) == 102)
{
   this.btn_JouninExam.visible = true;
   this.eventHandler.addListener(this.btn_JouninExam, MouseEvent.CLICK, this.openExternalPanel, false, 0, true);
}
if(int(Character.character_lvl) == 103)
{
   this.btn_SpecialJouninExam.visible = true;
   this.eventHandler.addListener(this.btn_SpecialJouninExam, MouseEvent.CLICK, this.openExternalPanel, false, 0, true);
}
if(int(Character.character_lvl) == 104)
{
   this.btn_NinjaTutorExam.visible = true;
   this.eventHandler.addListener(this.btn_NinjaTutorExam, MouseEvent.CLICK, this.openExternalPanel, false, 0, true);
}
```

---

## Server-Side Note

The server still runs its own rank/level guard on exam entry. At prestige levels 101+, a character's rank will be high enough to satisfy all existing exam rank requirements, so no server-side changes are needed.

---

## Removing for Production

Delete or comment out the four `if` blocks marked `// DEV ONLY` in `HUD.as:159–179`.
