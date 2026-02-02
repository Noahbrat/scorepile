# Phase: Separate Bidding from Score Entry

**Date:** 2026-02-01
**Status:** Proposed
**Origin:** Noah's idea — bidding and scoring happen at different times in a real game, the UI should reflect that.

## Problem

Currently, `FiveHundredRoundEntry.vue` presents bidding and trick-counting as a single form. You enter the bid, the tricks won, and save it all at once. But in a real game of 500:

1. **Bidding** happens first — a winner is declared with a suit/trick count
2. **Play** happens — hands are played out over several minutes
3. **Scoring** happens last — count tricks, calculate points

Cramming all three into one dialog means you either:
- Wait until the round is over to enter anything (losing the bid info temporarily)
- Have to remember the bid when entering scores later

## Proposed Design

### Round Sub-States

Rounds currently go: `in_progress` → `completed`. Add granularity:

```
bidding → playing → scoring → completed
```

| State | What's happening | User action |
|-------|-----------------|-------------|
| `bidding` | Players are bidding | Enter winning bid (team + bid) |
| `playing` | Hand is being played | No input needed — just a status indicator |
| `playing` | *(optional)* Could skip this state and go straight to `scoring` | — |
| `scoring` | Hand finished, count tricks | Enter tricks won |
| `completed` | Round saved with scores | View results |

### UX Flow

**Step 1: Record the Bid**
- Select bidding team
- Select bid from grid (same Avondale grid we have now)
- Save → round enters `playing` state
- UI shows "Round N in progress — [Team] bid 7♥ (200)"

**Step 2: Record the Result**
- When hand finishes, tap to enter tricks
- UI already knows the bid — just needs tricks won
- Score preview shows calculated points
- Save → round enters `completed` state

**Step 3: (Optional) Skip Playing State**
- For people who prefer the current all-at-once flow, allow going from `bidding` → `scoring` immediately
- "I already know the result" shortcut

### API Changes

#### Option A: Two-Step Round API
```
POST /api/games/{id}/rounds
  Body: { bid_key, bidder_team, bid_tricks, bid_suit }
  → Creates round in "playing" state

PATCH /api/games/{id}/rounds/{roundId}
  Body: { tricks_won: { team_1: X, team_2: Y } }
  → Engine calculates scores, moves to "completed"
```

#### Option B: Round Phases (more flexible)
```
POST /api/games/{id}/rounds
  Body: { phase: "bid", data: { bid_key, bidder_team, ... } }

PATCH /api/games/{id}/rounds/{roundId}/score
  Body: { tricks_won: { ... } }
```

**Recommendation:** Option A is simpler and matches the current REST pattern.

### Database Changes

`rounds` table additions:
- `status` enum: `bidding`, `playing`, `scoring`, `completed` (default: `completed` for backward compat)
- `bid_data` JSON column (or keep using `round_data` with the bid fields, add `tricks_won` on completion)

Or simply: the existing `round_data` JSON already stores everything. Just add a `status` field so the UI knows whether the bid has been entered but tricks haven't been counted yet.

### Frontend Changes

**`FiveHundredRoundEntry.vue`** splits into two sub-components:
1. `FiveHundredBidEntry.vue` — team selection + bid grid (step 1)
2. `FiveHundredScoreEntry.vue` — tricks won + score preview (step 2)

**`GameView.vue`** changes:
- "New Round" opens `BidEntry` dialog
- Active round shows bid info + "Enter Score" button
- Round history shows bid + result together

### Benefits

1. **Matches real gameplay** — enter data when it happens
2. **Bid tracking** — see who bid what, even before tricks are counted
3. **Simpler score entry** — when entering tricks, the bid context is already there
4. **Future features:**
   - Bidding history/stats (who overbids, who plays conservative)
   - "Needs X more tricks to make bid" indicator during play
   - Timer/duration tracking per round phase
   - Undo support (cancel a bid before tricks are entered)

### Migration Path

- Existing rounds (no `status` field) treated as `completed`
- New rounds get full lifecycle
- No breaking changes to stored data

### Scope Estimate

- **Backend:** ~2-3 hours (status field, two-step API, validation)
- **Frontend:** ~3-4 hours (split components, active round state, transitions)
- **Tests:** ~2 hours (backend engine tests, frontend component tests)
- **Total:** ~1-2 sessions of focused work

## Open Questions

1. Should the `playing` state be optional or always present? (Can simplify by going bid → scoring directly)
2. Do we want to support editing a bid after it's saved but before tricks are entered?
3. Should this extend to other game engines eventually, or is it 500-specific?
4. How to handle the "New Round" dialog — separate dialog vs. inline UI in the game view?

## Not In Scope (Yet)

- Real-time multiplayer (each player enters their own bid)
- Bid validation against previous bids (ensuring bid is higher than last)
- Full bidding round simulation (tracking all bids, not just the winner)
