// ═══════════════ KDS MOCK DATA — Functional MVP ════════════
//
// Kitchen state flow (the only layer KDS writes):
//     NEW → PREPARING → READY → SERVED
//
// Each ticket is ONE order instance and ONE type:
//     type: 'initial'  → the table's first serving
//     type: 'refill'   → a later add-on; its OWN ticket, own timer.
// An initial order and a refill for the same table are NEVER merged —
// when the initial is served it leaves the active board; a refill arrives
// as a brand-new card (see T-05 and T-07 below).
//
// No decorative badges. Urgency is read from the elapsed timer, not a badge:
//     0–15 min   normal
//     15–25 min  warning   (amber timer)
//     25+ min    overdue   (red timer + red card edge)
//
// Safety-critical modifiers (allergy/diet) are inlined into the item name
// and flagged `safety:true`. Prep-preference notes are omitted for MVP.

const TICKETS = [
  // ── INITIAL ORDERS ───────────────────────────────────────
  {
    id:'K-1184', table:'T-03', type:'initial', issued:'7:48 PM', elapsed: 38,
    state:'new',
    items:[
      { qty:2, name:'Plain Samgyupsal',     done:false },
      { qty:1, name:'Yangyeom Samgyupsal',   done:false },
      { qty:1, name:'Banchan Set',           done:false },
      { qty:2, name:'Iced Plum Tea',         done:false },
    ],
  },
  {
    id:'K-1183', table:'T-11', type:'initial', issued:'7:46 PM', elapsed: 142,
    state:'new',
    items:[
      { qty:3, name:'Woosamgyup',            done:false },
      { qty:2, name:'Hyangcho Woosamgyup',   done:false },
      { qty:2, name:'Beef Bulgogi',          done:false },
      { qty:1, name:'Banchan Set',           done:false },
      { qty:1, name:'Gyeran Jjim',           done:false },
      { qty:2, name:'Soju · Chamisul',       done:false },
      { qty:1, name:'Sprite 1L',             done:false },
    ],
  },
  {
    id:'K-1182', table:'T-05', type:'initial', issued:'7:23 PM', elapsed: 1620,  // 27:00 — overdue
    state:'preparing',
    items:[
      { qty:2, name:'Plain Samgyupsal',      done:true  },
      { qty:1, name:'Moksal',                done:true  },
      { qty:2, name:'Beef Bulgogi',          done:false },
      { qty:1, name:'Banchan Set',           done:true  },
      { qty:4, name:'San Mig Light',         done:true  },
    ],
  },
  {
    id:'K-1179', table:'T-09', type:'initial', issued:'7:32 PM', elapsed: 1080,  // 18:00 — warning
    state:'preparing',
    items:[
      { qty:2, name:'Plain Samgyupsal',                  done:true  },
      { qty:2, name:'Beef Bulgogi — no garlic',          done:false, safety:true },
      { qty:1, name:'Banchan Set — omit shrimp paste',   done:true,  safety:true },
      { qty:2, name:'Iced Plum Tea',                     done:true  },
    ],
  },
  {
    id:'K-1177', table:'T-06', type:'initial', issued:'7:40 PM', elapsed: 600,   // 10:00 — normal
    state:'preparing',
    items:[
      { qty:1, name:'Plain Samgyupsal',      done:true  },
      { qty:1, name:'Yangyeom Samgyupsal',   done:false },
      { qty:1, name:'Banchan Set',           done:true  },
      { qty:1, name:'Coke Zero 500ml',       done:true  },
    ],
  },
  {
    id:'K-1178', table:'T-01', type:'initial', issued:'7:26 PM', elapsed: 1440,  // 24:00 — warning
    state:'ready',
    items:[
      { qty:2, name:'Woosamgyup',                done:true },
      { qty:2, name:'Hyangcho Woosamgyup',       done:true },
      { qty:1, name:'Golden Mushroom Beef Roll', done:true },
      { qty:1, name:'Dubu Ganjeong',             done:true },
      { qty:2, name:'Cass Fresh',                done:true },
    ],
  },
  {
    id:'K-1176', table:'T-08', type:'initial', issued:'7:36 PM', elapsed: 840,   // 14:00 — normal
    state:'ready',
    items:[
      { qty:2, name:'Korean Chili Samgyupsal', done:true },
      { qty:1, name:'Moksal',                  done:true },
      { qty:1, name:'Banchan Set',             done:true },
      { qty:1, name:'Gyeran Jjim',             done:true },
    ],
  },
  {
    id:'K-1180', table:'T-02', type:'initial', issued:'7:38 PM', elapsed: 720,   // 12:00 — normal
    state:'ready',
    items:[
      { qty:2, name:'Plain Samgyupsal',     done:true },
      { qty:1, name:'Yangyeom Samgyupsal',  done:true },
      { qty:1, name:'Moksal',               done:true },
      { qty:1, name:'Woosoo Cheese (melt)', done:true },
      { qty:1, name:'Banchan Set',          done:true },
    ],
  },
  {
    id:'K-1174', table:'T-12', type:'initial', issued:'7:50 PM', elapsed: 12,
    state:'new', new:true,
    items:[
      { qty:1, name:'Plain Samgyupsal', done:false },
      { qty:1, name:'Beef Bulgogi',     done:false },
      { qty:1, name:'Banchan Set',      done:false },
      { qty:2, name:'Iced Plum Tea',    done:false },
    ],
  },
  {
    id:'K-1175', table:'T-04', type:'initial', issued:'7:08 PM', elapsed: 2400,
    state:'served',
    items:[
      { qty:4, name:'Bingsu · Mango',  done:true },
      { qty:2, name:'Hot Barley Tea',  done:true },
    ],
  },

  // ── REFILLS (separate tickets; same tables, own timers) ───
  {
    id:'K-1181', table:'T-07', type:'refill', issued:'7:53 PM', elapsed: 260,   // 4:20
    state:'preparing',
    items:[
      { qty:2, name:'Yangyeom Samgyupsal',   done:false },
      { qty:1, name:'Korean Chili Samgyupsal',done:false },
      { qty:1, name:'Pickled Radish',        done:false },
      { qty:1, name:'Lettuce Wrap',          done:false },
    ],
  },
  {
    id:'K-1188', table:'T-05', type:'refill', issued:'7:55 PM', elapsed: 60,    // 1:00 — refill for the overdue T-05 initial
    state:'new',
    items:[
      { qty:2, name:'Kimchi',         done:false },
      { qty:1, name:'Pickled Radish', done:false },
    ],
  },
  {
    id:'K-1173', table:'T-10', type:'refill', issued:'7:54 PM', elapsed: 95,    // 1:35
    state:'new',
    items:[
      { qty:2, name:'Kimchi',              done:false },
      { qty:1, name:'Lettuce Wrap',        done:false },
      { qty:1, name:'Yangyeom Samgyupsal', done:false },
    ],
  },

  // ── COMPLETED (served / bumped off the active board) ──────
  {
    id:'K-1169', table:'T-16', type:'initial', issued:'7:02 PM', elapsed: 3120,
    state:'served',
    items:[
      { qty:2, name:'Woosamgyup',          done:true },
      { qty:1, name:'Beef Bulgogi',        done:true },
      { qty:1, name:'Banchan Set',         done:true },
      { qty:3, name:'Cass Fresh',          done:true },
    ],
  },

  // ── VOIDED (cancelled by front-of-house — kitchen stops) ──
  {
    id:'K-1171', table:'T-14', type:'initial', issued:'7:31 PM', elapsed: 1015,
    state:'voided', voidReason:'Cancelled by server — table walked',
    items:[
      { qty:2, name:'Plain Samgyupsal', done:false },
      { qty:1, name:'Moksal',           done:false },
      { qty:1, name:'Banchan Set',      done:false },
    ],
  },
  {
    id:'K-1187', table:'T-13', type:'refill', issued:'7:51 PM', elapsed: 305,
    state:'voided', voidReason:'Duplicate fire — voided by FOH',
    items:[
      { qty:1, name:'Yangyeom Samgyupsal', done:false },
      { qty:1, name:'Pickled Radish',      done:false },
    ],
  },
];

// ── Kitchen-stage metadata (NEW → PREPARING → READY → SERVED) ──
// `gate:'allItems'` → the forward action is blocked until every item is checked.
const STATES = [
  { id:'new',       name:'New',       color:'var(--st-new)',    bg:'var(--st-new-m)',    bd:'var(--st-new-b)',
    next:'preparing', action:'Start Preparing', gate:null },
  { id:'preparing', name:'Preparing', color:'var(--st-prep)',   bg:'var(--st-prep-m)',   bd:'var(--st-prep-b)',
    next:'ready',     action:'Mark Ready',      gate:'allItems' },
  { id:'ready',     name:'Ready',     color:'var(--st-ready)',  bg:'var(--st-ready-m)',  bd:'var(--st-ready-b)',
    next:'served',    action:'Mark Served',     gate:null },
  { id:'served',    name:'Completed',  color:'var(--st-served)', bg:'var(--st-served-m)', bd:'var(--st-served-b)',
    next:null,        action:null,              gate:null, terminal:true, recall:true,
    note:'Removed from active board' },
  { id:'voided',    name:'Voided',    color:'var(--void)',     bg:'var(--void-m)',     bd:'var(--void-b)',
    next:null,        action:null,              gate:null, terminal:true, recall:true,
    note:'Cancelled — kitchen stopped' },
];

// Card sort order: Overdue → Preparing → Ready → New → Served; ties = oldest first.
const STAGE_SORT = { preparing:0, ready:1, new:2, served:3, voided:4 };

// Elapsed-time thresholds (seconds)
const WARN_TARGET = 15 * 60;  // 15 min — amber
const OVER_TARGET = 25 * 60;  // 25 min — red / overdue

const fmtElapsed = (s) => {
  const m = Math.floor(s / 60);
  const sec = (s % 60).toString().padStart(2,'0');
  return `${m}:${sec}`;
};

// Overdue/warning applies only to active tickets (not completed or voided).
const ticketUrgency = (t) => {
  if (t.state === 'served' || t.state === 'voided') return 'ok';
  if (t.elapsed >= OVER_TARGET) return 'over';
  if (t.elapsed >= WARN_TARGET) return 'warn';
  return 'ok';
};

window.KDS = {
  TICKETS, STATES, STAGE_SORT, WARN_TARGET, OVER_TARGET,
  fmtElapsed, ticketUrgency,
};
