const { useState, useEffect, useMemo, useRef } = React;
const { TICKETS, STATES, STAGE_SORT, WARN_TARGET, OVER_TARGET,
  fmtElapsed, ticketUrgency } = window.KDS;

// ═══════════════ ICONS ════════════════════════════════════
const Ico = ({ n, size = 14 }) => {
  const ps = { width: size, height: size, viewBox: '0 0 24 24', fill: 'none',
    stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' };
  const paths = {
    check: <polyline points="20 6 9 17 4 12" />,
    fire: <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" />,
    arrow: <><line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" /></>,
    lock: <><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></>,
    undo: <><path d="M9 14L4 9l5-5" /><path d="M4 9h11a5 5 0 0 1 0 10h-4" /></>,
    ban: <><circle cx="12" cy="12" r="9" /><line x1="5.6" y1="5.6" x2="18.4" y2="18.4" /></>,
    x: <><path d="M18 6L6 18" /><path d="M6 6l12 12" /></>
  };
  return <svg {...ps}>{paths[n]}</svg>;
};

// ═══════════════ CLOCK ═══════════════════════════════════
const useClock = () => {
  const [t, setT] = useState(new Date(2026, 5, 6, 19, 56, 0));
  useEffect(() => {
    const id = setInterval(() => setT((d) => new Date(d.getTime() + 1000)), 1000);
    return () => clearInterval(id);
  }, []);
  return t;
};

// ═══════════════ COMMAND BAR ═════════════════════════════
const CmdBar = ({ rush, tickets }) => {
  const t = useClock();
  const hh = t.getHours() % 12 || 12;
  const mm = t.getMinutes().toString().padStart(2, '0');
  const ap = t.getHours() >= 12 ? 'PM' : 'AM';
  const date = t.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });

  const c = useMemo(() => ({
    live: tickets.filter((x) => ['new', 'preparing', 'ready'].includes(x.state)).length,
    neu: tickets.filter((x) => x.state === 'new').length,
    prep: tickets.filter((x) => x.state === 'preparing').length,
    ready: tickets.filter((x) => x.state === 'ready').length,
    over: tickets.filter((x) => ticketUrgency(x) === 'over').length
  }), [tickets]);

  return (
    <div className="cmdbar">
      <div className="brand">
        <div className="brand-mark"><img src="images/woosoo-icon.png" alt="" /></div>
        <div className="brand-text">
          <span className="brand-name">Woosoo</span>
          <span className="brand-sub">Kitchen · Live</span>
        </div>
      </div>

      <div className="cmd-stats">
        <div className="stat"><span className="stat-val">{c.live}</span><span className="stat-lbl">Active</span></div>
        <div className="stat-div" />
        <div className="stat"><span className="stat-val new">{c.neu}</span><span className="stat-lbl">New</span></div>
        <div className="stat"><span className="stat-val prep">{c.prep}</span><span className="stat-lbl">Preparing</span></div>
        <div className="stat"><span className="stat-val rdy">{c.ready}</span><span className="stat-lbl">Ready</span></div>
        <div className="stat-div" />
        <div className="stat"><span className="stat-val over">{c.over}</span><span className="stat-lbl">Overdue</span></div>
      </div>

      <div className="cmd-right">
        {rush ?
        <span className="rush-tag"><Ico n="fire" size={11} />Rush</span> :
        <span className="live-tag"><span className="live-dot" />Online</span>}
        <div className="clock">
          <span className="clock-time">{hh}:{mm} {ap}</span>
          <span className="clock-date">{date}</span>
        </div>
      </div>
    </div>);

};

// ═══════════════ FILTER CHIPS ════════════════════════════
const FilterChips = ({ filter, setFilter, tickets }) => {
  const groups = [
  { id: 'all', name: 'All Active', cls: 'all', match: (t) => ['new', 'preparing', 'ready'].includes(t.state) },
  { id: 'overdue', name: 'Overdue', cls: 'overdue', warn: true, match: (t) => ticketUrgency(t) === 'over' },
  { div: true },
  { id: 'new', name: 'New', cls: 'new', match: (t) => t.state === 'new' },
  { id: 'preparing', name: 'Preparing', cls: 'preparing', match: (t) => t.state === 'preparing' },
  { id: 'ready', name: 'Ready', cls: 'ready', match: (t) => t.state === 'ready' },
  { div: true },
  { id: 'completed', name: 'Completed', cls: 'served', match: (t) => t.state === 'served' },
  { id: 'voided', name: 'Voided', cls: 'voided', match: (t) => t.state === 'voided' }];

  return (
    <div className="chips">
      {groups.map((g, i) => {
        if (g.div) return <div key={`d${i}`} className="chip-div" />;
        const count = tickets.filter(g.match).length;
        const on = filter === g.id;
        return (
          <div key={g.id} className={`chip ${on ? 'on' : ''} ${g.cls}`} onClick={() => setFilter(g.id)}>
            {g.warn && <Ico n="fire" size={11} />}
            <span className="chip-name">{g.name}</span>
            <span className="chip-count">{count}</span>
          </div>);

      })}
    </div>);

};

// ═══════════════ TICKET CARD ═════════════════════════════
// Answers only the five questions: which table · initial or refill ·
// how long waiting · what items · next valid action.
const TicketCard = ({ t, onAction, onItemToggle, onRecall, onBlock }) => {
  const urg = ticketUrgency(t);
  const totalQty = t.items.reduce((s, i) => s + i.qty, 0);
  const doneQty = t.items.filter((i) => i.done).reduce((s, i) => s + i.qty, 0);
  const allDone = doneQty === totalQty;

  const stage = STATES.find((s) => s.id === t.state) || STATES[0];
  const gated = stage.gate === 'allItems' && !allDone;
  const terminal = !!stage.terminal;

  const handle = () => {
    if (!stage.next) return;
    if (gated) {onBlock('Complete all checklist items first.');return;}
    onAction(t.id, stage.next);
  };

  return (
    <div className={`tk s-${t.state} u-${urg}`}>
      <div className="tk-head">
        <div className="tk-id">
          <span className="tk-kicker">Table</span>
          <span className="tk-table-num">{t.table}</span>
          <span className="tk-orderid">Order {t.id}</span>
        </div>
        <div className="tk-timer">
          <span className="tk-kicker">Elapsed</span>
          <span className={`tk-elapsed u-${urg}`}>{fmtElapsed(t.elapsed)}</span>
          <span className="tk-issued">Issued {t.issued}</span>
        </div>
      </div>

      <div className="tk-typerow">
        <span className={`tk-type type-${t.type}`}>{t.type === 'refill' ? 'Refill' : 'Initial Order'}</span>
        {t.recalled > 0 && <span className="tk-recalled"><Ico n="undo" size={11} />Recalled ×{t.recalled}</span>}
      </div>
      {t.state === 'voided' && t.voidReason &&
      <div className="tk-voidreason"><Ico n="ban" size={13} />{t.voidReason}</div>}

      <div className="tk-items-head">
        <span>Items</span>
        <span className={`tk-count ${allDone ? 'all' : ''}`}>{doneQty} / {totalQty} checked</span>
      </div>

      <div className="tk-items">
        {t.items.map((i, idx) => {
          const [base, mod] = i.safety && i.name.includes(' — ') ?
          [i.name.split(' — ')[0], i.name.split(' — ')[1]] : [i.name, null];
          return (
            <div key={idx} className={`item ${i.done ? 'done' : ''}`} onClick={() => !terminal && onItemToggle(t.id, idx)}>
              <div className="item-check">{i.done && <Ico n="check" size={13} />}</div>
              <span className="item-qty">{i.qty}×</span>
              <span className="item-name">
                {base}{mod && <span className="item-safety"> — {mod}</span>}
              </span>
            </div>);

        })}
      </div>

      <div className="tk-foot">
        <div className="tk-status">
          <span className="tk-status-lbl">Status</span>
          <span className={`sbadge s-${t.state}`}><span className="sbadge-dot" />{stage.name}</span>
        </div>
        {stage.action ?
        <button
          className={`tk-btn ${t.state === 'ready' ? 'go' : 'primary'} ${gated ? 'locked' : ''}`}
          onClick={handle}>
              {gated && <Ico n="lock" size={13} />}
              {stage.action}
              {!gated && <Ico n="arrow" size={14} />}
            </button> :
        stage.recall ?
        <button className="tk-btn recall" onClick={() => onRecall(t.id)}>
              <Ico n="undo" size={14} />Recall to Line
            </button> :
        <div className="tk-done-note">{stage.note || 'Removed from active board'}</div>}
      </div>
    </div>);

};

// ═══════════════ QUEUE (main view) ═══════════════════════
const Queue = ({ tickets, filter, density, onAction, onItemToggle, onRecall, onBlock }) => {
  const filtered = useMemo(() => {
    let list = [...tickets];
    if (filter === 'all') list = list.filter((t) => ['new', 'preparing', 'ready'].includes(t.state));else
    if (filter === 'overdue') list = list.filter((t) => ticketUrgency(t) === 'over');else
    if (filter === 'completed') list = list.filter((t) => t.state === 'served');else
    list = list.filter((t) => t.state === filter);

    // Overdue → Preparing → Ready → New → Served; ties = oldest first.
    return list.sort((a, b) => {
      const ao = ticketUrgency(a) === 'over' ? -1 : 0;
      const bo = ticketUrgency(b) === 'over' ? -1 : 0;
      if (ao !== bo) return ao - bo;
      const so = (STAGE_SORT[a.state] ?? 9) - (STAGE_SORT[b.state] ?? 9);
      if (so !== 0) return so;
      return b.elapsed - a.elapsed;
    });
  }, [tickets, filter]);

  if (filtered.length === 0) {
    return (
      <div className="grid-wrap">
        <div className="empty">
          <Ico n="check" size={34} />
          <div className="empty-title">Queue clear</div>
          <div className="empty-sub">Nothing in this view — kitchen's caught up.</div>
        </div>
      </div>);

  }

  return (
    <div className="grid-wrap">
      <div className={`grid ${density === 'compact' ? 'compact' : ''}`}>
        {filtered.map((t) =>
        <TicketCard key={t.id} t={t}
        onAction={onAction} onItemToggle={onItemToggle} onRecall={onRecall} onBlock={onBlock} />
        )}
      </div>
    </div>);

};

// ═══════════════ TWEAKS PANEL ════════════════════════════
const Tweaks = ({ open, setOpen, density, setDensity, rush, setRush }) => {
  if (!open) return null;
  return (
    <div className="tweaks vis">
      <div className="tw-head">
        <span className="tw-title">Tweaks</span>
        <div className="tw-x" onClick={() => setOpen(false)}><Ico n="x" size={11} /></div>
      </div>
      <div className="tw-body">
        <div className="tw-row">
          <span className="tw-lbl">Density</span>
          <div className="tw-segs">
            <div className={`tw-seg ${density === 'comfortable' ? 'on' : ''}`} onClick={() => setDensity('comfortable')}>Comfortable</div>
            <div className={`tw-seg ${density === 'compact' ? 'on' : ''}`} onClick={() => setDensity('compact')}>Compact</div>
          </div>
        </div>
        <div className="tw-toggle">
          <span className="tw-toggle-lbl">Rush hour</span>
          <div className={`tw-sw ${rush ? 'on' : ''}`} onClick={() => setRush(!rush)} />
        </div>
      </div>
    </div>);

};

// ═══════════════ APP ROOT ════════════════════════════════
const DEFAULTS = /*EDITMODE-BEGIN*/{
  "filter": "all",
  "density": "comfortable",
  "rush": false
} /*EDITMODE-END*/;

const App = () => {
  const [filter, setFilter] = useState(DEFAULTS.filter);
  const [density, setDensity] = useState(DEFAULTS.density);
  const [rush, setRush] = useState(DEFAULTS.rush);
  const [tweaksOpen, setTweaksOpen] = useState(false);
  const [tickets, setTickets] = useState(TICKETS);
  const [toast, setToast] = useState(null);
  const toastT = useRef(null);

  const flash = (msg) => {
    setToast(msg);
    clearTimeout(toastT.current);
    toastT.current = setTimeout(() => setToast(null), 2200);
  };

  // Tweaks host protocol
  useEffect(() => {
    const onMsg = (e) => {
      if (!e.data) return;
      if (e.data.type === '__activate_edit_mode') setTweaksOpen(true);
      if (e.data.type === '__deactivate_edit_mode') setTweaksOpen(false);
    };
    window.addEventListener('message', onMsg);
    window.parent.postMessage({ type: '__edit_mode_available' }, '*');
    return () => window.removeEventListener('message', onMsg);
  }, []);

  const persist = (edits) => {
    try {window.parent.postMessage({ type: '__edit_mode_set_keys', edits }, '*');} catch {}
  };
  const wrap = (setter, key) => (v) => {setter(v);persist({ [key]: v });};

  // Tick elapsed every second (frozen once served)
  useEffect(() => {
    const id = setInterval(() => {
      setTickets((ts) => ts.map((t) => t.state === 'served' || t.state === 'voided' ? t : { ...t, elapsed: t.elapsed + 1 }));
    }, 1000);
    return () => clearInterval(id);
  }, []);

  const onItemToggle = (tid, idx) => {
    setTickets((ts) => ts.map((t) => t.id !== tid ? t : {
      ...t,
      items: t.items.map((i, k) => k === idx ? { ...i, done: !i.done } : i)
    }));
  };

  // KDS mutates kitchen status only. Mark Ready is gated on the card
  // (all items checked); transitions never auto-check items.
  const onAction = (tid, next) => {
    setTickets((ts) => ts.map((t) => t.id !== tid ? t : { ...t, state: next }));
  };

  // Recall: pull a completed/voided ticket back onto the line as a re-fire.
  const onRecall = (tid) => {
    setTickets((ts) => ts.map((t) => t.id !== tid ? t : {
      ...t, state: 'preparing', recalled: (t.recalled || 0) + 1, voidReason: undefined
    }));
  };

  return (
    <div className="kds" data-screen-label="KDS Order Queue">
      <CmdBar rush={rush} tickets={tickets} />
      <FilterChips filter={filter} setFilter={wrap(setFilter, 'filter')} tickets={tickets} />
      <Queue tickets={tickets} filter={filter} density={density}
      onAction={onAction} onItemToggle={onItemToggle} onRecall={onRecall} onBlock={flash} />

      {toast && <div className="toast"><Ico n="lock" size={13} />{toast}</div>}

      <Tweaks open={tweaksOpen} setOpen={(o) => {
        setTweaksOpen(o);
        if (!o) window.parent.postMessage({ type: '__edit_mode_dismissed' }, '*');
      }}
      density={density} setDensity={wrap(setDensity, 'density')}
      rush={rush} setRush={wrap(setRush, 'rush')} />
    </div>);

};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);