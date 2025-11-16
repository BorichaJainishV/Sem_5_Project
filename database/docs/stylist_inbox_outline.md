# Stylist in Your Inbox – Quiz Outline

## Purpose
Map a lightweight onboarding quiz to the existing persona attributes (`style`, `palette`, `goal`) so we can reuse current recommendation logic and fuel the curated email/on-site experience.

---

## Question Flow

### 1. Style Vibe Check
- **Prompt**: "Which vibe feels closest to your go-to outfits?"
- **UI**: 3 selectable cards with imagery/icons.
- **Options**:
  1. **Urban Creator** — value `street`
     - Copy: "Oversized layers, street graphics, limited drops."
  2. **Clean Classic** — value `minimal`
     - Copy: "Tailored basics, elevated essentials, neutral fits."
  3. **Statement Maker** — value `bold`
     - Copy: "High-impact prints, neon hits, spotlight pieces."
- **Tie-in**: Persists to `style_choice` in `style_quiz_results` and drives `style` matching in `inventory_quiz_tags`.

### 2. Palette Energy
- **Prompt**: "Which color story are you leaning into right now?"
- **UI**: Swatch grid (monochrome | earth | vivid) with short descriptors.
- **Options**:
  1. **Monochrome Sleek** — value `monochrome`
     - Copy: "Black, white, charcoal with sharp contrasts."
  2. **Earthy Balance** — value `earth`
     - Copy: "Clay, olive, sand with natural texture."
  3. **Vivid Gradient** — value `vivid`
     - Copy: "Sunset gradients, electric brights, color-pop moments."
- **Tie-in**: Persists to `palette_choice` and feeds palette filters already powering shop personas.

### 3. Goal for This Capsule
- **Prompt**: "What are you dreaming up with these pieces?"
- **UI**: Segmented buttons with iconography.
- **Options**:
  1. **Dialed Everyday Rotation** — value `everyday`
     - Copy: "Build reliable looks you can remix all week."
  2. **Launch-Ready Merch Drop** — value `launch`
     - Copy: "Prep limited merch or event-exclusive heat."
  3. **Premium Gift Kit** — value `gift`
     - Copy: "Surprise someone with a custom bundle that feels luxe."
- **Tie-in**: Persists to `goal_choice` and syncs with existing `goal_tags` scoring logic.

---

## Result Payload
- Persona label & summary already computed via `derivePersonaLabel` (e.g., "Urban Creator Bundle").
- Recommendations reuse existing `buildRecommendations` output; we’ll merely reformat for email/dashboard.
- Store quiz timestamp and CTA source (`inbox_flow`) in session for analytics (to be added alongside handler updates).

---

## Next Steps
1. Build condensed quiz UI component (modal or standalone page section) that posts to `style_quiz_handler.php` using the above enumerations.
2. Persist additional metadata (timestamp, entry source) to `style_quiz_results` without breaking current schema.
3. Design email/on-site template that consumes `{persona_label, persona_summary, recommendations}` and schedules follow-up content.
4. Deliver admin tagging console so merch can curate `inventory_quiz_tags` without SQL access (see requirements below).

---

## Admin Tagging Console Requirements
- **Entry List View**: show all active inventory rows with current `style_tags`, `palette_tags`, and `goal_tags`; support search/filter by product name or tag keyword.
- **Inline Editor**: provide multi-select or token-input controls for each tag family, backed by the canonical vocab (`street|minimal|bold`, `monochrome|earth|vivid`, `everyday|launch|gift`). Prevent freeform strings.
- **Create-on-Publish**: when a new inventory item is saved via `products.php`, surface a call-to-action prompting tag assignment, defaulting to the seeded baseline until merch updates.
- **Validation & Guidance**: display quick descriptions of each tag to keep categorization consistent, highlight any inventory missing at least one tag per family, and block save if a field is left empty.
- **Audit Trail**: reuse `activity_logger.php` to capture before/after tag changes along with the admin ID and timestamp for compliance.
- **API Endpoint**: add lightweight PHP endpoint (e.g., `admin/update_inventory_tags.php`) returning JSON so the UI can update tags asynchronously and provide instant feedback.
