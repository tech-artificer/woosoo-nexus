// @ts-nocheck
const { useState, useMemo, useEffect, useRef } = React;

// ── MOCK DATA ─────────────────────────────────────────────────────────────────
const INIT_PKGS = [
  { id:1, name:'Set Meal A', description:'Main dish + drink + side of your choice', krypton_menu_id:1042, is_active:true, sort_order:1,
    modifiers:[{krypton_menu_id:101,sort_order:0},{krypton_menu_id:102,sort_order:1},{krypton_menu_id:105,sort_order:2},{krypton_menu_id:103,sort_order:3}] },
  { id:2, name:'Set Meal B', description:'Premium selection with complimentary dessert', krypton_menu_id:1043, is_active:true, sort_order:2,
    modifiers:[{krypton_menu_id:104,sort_order:0},{krypton_menu_id:106,sort_order:1},{krypton_menu_id:108,sort_order:2}] },
  { id:3, name:'Family Pack', description:'Feeds 4–6 guests, served family-style', krypton_menu_id:1044, is_active:false, sort_order:3,
    modifiers:[{krypton_menu_id:101,sort_order:0},{krypton_menu_id:107,sort_order:1},{krypton_menu_id:108,sort_order:2},{krypton_menu_id:109,sort_order:3},{krypton_menu_id:110,sort_order:4}] },
];

const MENU_OPTS = [
  { id:101, name:'Fried Chicken',    receipt_name:'FRD CHKN' },
  { id:102, name:'Steamed Rice',     receipt_name:'STM RICE' },
  { id:103, name:'House Salad',      receipt_name:'HSE SLAD' },
  { id:104, name:'Grilled Salmon',   receipt_name:'GRL SLMN' },
  { id:105, name:'Soft Drink',       receipt_name:'SFT DRNK' },
  { id:106, name:'Iced Tea',         receipt_name:'ICD TEA'  },
  { id:107, name:'Garlic Bread',     receipt_name:'GRL BRD'  },
  { id:108, name:'Soup of the Day',  receipt_name:'SOUP DAY' },
  { id:109, name:'Spring Rolls',     receipt_name:'SPR ROLL' },
  { id:110, name:'Mango Shake',      receipt_name:'MNG SHKE' },
  { id:111, name:'Leche Flan',       receipt_name:'LCH FLAN' },
  { id:112, name:'Buko Pandan',      receipt_name:'BKO PNDN' },
  { id:1042, name:'Set Meal A (Menu)', receipt_name:'SET-A'  },
  { id:1043, name:'Set Meal B (Menu)', receipt_name:'SET-B'  },
  { id:1044, name:'Family Pack (Menu)', receipt_name:'FAM-PK'},
];

const INIT_CATS = [
  { id:1, name:'Mains',     slug:'mains',     sort_order:1, is_active:true  },
  { id:2, name:'Sides',     slug:'sides',     sort_order:2, is_active:true  },
  { id:3, name:'Beverages', slug:'beverages', sort_order:3, is_active:true  },
  { id:4, name:'Desserts',  slug:'desserts',  sort_order:4, is_active:false },
  { id:5, name:'Grilled',   slug:'grilled',   sort_order:5, is_active:true  },
];

// ── ICONS ─────────────────────────────────────────────────────────────────────
function Ic({d, size=14}) {
  return <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={d}/></svg>;
}
const IPlus    = () => <Ic d="M12 5v14M5 12h14"/>;
const IPencil  = () => <Ic d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>;
const ITrash   = () => <Ic d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>;
const IPkg     = () => <Ic d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16zM3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/>;
const IList    = () => <Ic d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>;
const ISearch  = () => <Ic d="M21 21l-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0"/>;
const IReset   = () => <Ic d="M3 2v6h6M3.51 9a9 9 0 1 0 .49-3.09"/>;
const IBook    = () => <Ic d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15z"/>;
const ICode    = () => <Ic d="M16 18l6-6-6-6M8 6l-6 6 6 6"/>;
const IDash    = () => <Ic d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>;
const IOrders  = () => <Ic d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/>;
const IMenus   = () => <Ic d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>;
const IInfo    = () => <Ic d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 8v4M12 16h.01"/>;
const ICheck   = () => <Ic d="M20 6L9 17l-5-5"/>;
const ICopy    = () => <Ic d="M8 17.929H6c-1.105 0-2-.912-2-2.036V5.036C4 3.91 4.895 3 6 3h8c1.105 0 2 .911 2 2.036v1.866m-6 .17h8c1.105 0 2 .91 2 2.035v10.857C20 21.09 19.105 22 18 22h-8c-1.105 0-2-.911-2-2.036V9.107c0-1.124.895-2.036 2-2.036z"/>;

function GripIcon() {
  return (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
      <circle cx="9" cy="5"  r="1.5"/><circle cx="9"  cy="12" r="1.5"/><circle cx="9"  cy="19" r="1.5"/>
      <circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
    </svg>
  );
}

// ── SHARED ATOMS ──────────────────────────────────────────────────────────────
function Toggle({ value, onChange }) {
  return (
    <button className={`tog ${value ? 'on' : ''}`} onClick={() => onChange(!value)}>
      <div className="tog-knob" />
    </button>
  );
}

function Field({ label, hint, children }) {
  return (
    <div>
      {label && <label className="form-label">{label}</label>}
      {children}
      {hint && <div className="form-hint">{hint}</div>}
    </div>
  );
}

function Confirm({ open, title, message, onConfirm, onCancel }) {
  if (!open) return null;
  return (
    <div className="modal-wrap" onClick={onCancel}>
      <div className="modal" style={{width:400}} onClick={e => e.stopPropagation()}>
        <div className="modal-head">
          <div className="modal-title" style={{color:'var(--red)'}}>{title}</div>
          <button className="btn ghost icon-only sm" onClick={onCancel}>✕</button>
        </div>
        <div className="modal-body" style={{gap:0}}>
          <p className="guide-p">{message}</p>
        </div>
        <div className="modal-foot">
          <button className="btn" onClick={onCancel}>Cancel</button>
          <button className="btn danger" onClick={onConfirm}>Delete</button>
        </div>
      </div>
    </div>
  );
}

// ── SIDEBAR ───────────────────────────────────────────────────────────────────
function Sidebar({ page, onPage }) {
  const updated = [
    { id:'packages',   icon:<IPkg/>,   label:'Packages' },
    { id:'categories', icon:<IList/>,  label:'Tablet Categories' },
  ];
  const docs = [
    { id:'guide',  icon:<IBook/>, label:'Operation Guide' },
    { id:'prompt', icon:<ICode/>, label:'Developer Prompt' },
  ];
  const dim = [
    { icon:<IDash/>,   label:'Dashboard' },
    { icon:<IOrders/>, label:'Orders' },
    { icon:<IMenus/>,  label:'Menus' },
  ];
  return (
    <div className="sidebar">
      <div className="logo">
        <div style={{width:26,height:26,borderRadius:5,background:'linear-gradient(135deg,#F6B56D,#B08047)',flexShrink:0}}/>
        <div className="col g4">
          <div className="logo-mark">Woosoo</div>
          <div className="logo-sub">Nexus Admin</div>
        </div>
      </div>

      <div className="nav-label">Redesigned</div>
      {updated.map(n => (
        <div key={n.id} className={`nav-item ${page===n.id?'active':''}`} onClick={() => onPage(n.id)}>
          {n.icon}<span>{n.label}</span>
          {page===n.id && <span className="nav-badge">v2</span>}
        </div>
      ))}

      <div className="nav-label" style={{marginTop:4}}>Docs</div>
      {docs.map(n => (
        <div key={n.id} className={`nav-item ${page===n.id?'active':''}`} onClick={() => onPage(n.id)}>
          {n.icon}<span>{n.label}</span>
        </div>
      ))}

      <div className="nav-label" style={{marginTop:4}}>Other Pages</div>
      {dim.map((n,i) => (
        <div key={i} className="nav-item dim">{n.icon}<span>{n.label}</span></div>
      ))}

      <div className="sb-footer">
        <div className="avatar">AD</div>
        <div className="col g4">
          <span style={{fontSize:12,fontWeight:600,color:'var(--fg0)',fontFamily:'var(--font-d)'}}>Admin</span>
          <span style={{fontSize:11,color:'var(--accent)',fontFamily:'var(--font-d)',letterSpacing:'.04em'}}>Woosoo Nexus</span>
        </div>
      </div>
    </div>
  );
}

// ── PACKAGES PAGE ─────────────────────────────────────────────────────────────
const EMPTY_FORM = { name:'', description:'', krypton_menu_id:0, is_active:true, sort_order:0, modifier_ids:[] };

function PackagesPage() {
  const [packages, setPackages]   = useState(INIT_PKGS);
  const [editingId, setEditingId] = useState(null);
  const [form, setForm]           = useState({...EMPTY_FORM});
  const [modSearch, setModSearch] = useState('');
  const [confirm, setConfirm]     = useState(null);
  const nextId = useRef(100);

  const menuById = useMemo(() => new Map(MENU_OPTS.map(m => [m.id, m])), []);

  const filteredMods = useMemo(() => {
    const q = modSearch.trim().toLowerCase();
    if (!q) return MENU_OPTS;
    return MENU_OPTS.filter(m =>
      m.name.toLowerCase().includes(q) ||
      String(m.id).includes(q) ||
      (m.receipt_name||'').toLowerCase().includes(q)
    );
  }, [modSearch]);

  const selectedMods = useMemo(() =>
    form.modifier_ids.map(id => menuById.get(id)).filter(Boolean),
    [form.modifier_ids, menuById]
  );

  function editPkg(pkg) {
    setEditingId(pkg.id);
    setForm({
      name: pkg.name,
      description: pkg.description || '',
      krypton_menu_id: pkg.krypton_menu_id,
      is_active: pkg.is_active,
      sort_order: pkg.sort_order,
      modifier_ids: [...pkg.modifiers].sort((a,b)=>a.sort_order-b.sort_order).map(m=>m.krypton_menu_id),
    });
    setModSearch('');
  }

  function resetForm() { setEditingId(null); setForm({...EMPTY_FORM}); setModSearch(''); }

  function toggleMod(id, checked) {
    setForm(f => ({
      ...f,
      modifier_ids: checked ? [...f.modifier_ids, id] : f.modifier_ids.filter(x => x !== id)
    }));
  }

  function removeMod(id) {
    setForm(f => ({...f, modifier_ids: f.modifier_ids.filter(x => x !== id)}));
  }

  function savePkg() {
    if (!form.name.trim() || !form.krypton_menu_id) return;
    const modifiers = form.modifier_ids.map((id,i) => ({krypton_menu_id:id, sort_order:i}));
    if (editingId) {
      setPackages(ps => ps.map(p => p.id===editingId ? {...p, ...form, modifiers} : p));
    } else {
      setPackages(ps => [...ps, {id: nextId.current++, ...form, modifiers}]);
    }
    resetForm();
  }

  function deletePkg(pkg) {
    setPackages(ps => ps.filter(p => p.id !== pkg.id));
    if (editingId === pkg.id) resetForm();
    setConfirm(null);
  }

  function toggleActive(id) {
    setPackages(ps => ps.map(p => p.id===id ? {...p, is_active:!p.is_active} : p));
  }

  const sorted = [...packages].sort((a,b) => a.sort_order - b.sort_order);
  const canSave = form.name.trim() && form.krypton_menu_id > 0;

  return (
    <div className="content">
      <div className="page-head">
        <div>
          <div className="page-title">Packages</div>
          <div className="page-sub">Map set-meal bundles to their POS modifier items · changes apply to new orders</div>
        </div>
        <div className="row g8">
          <span className="pill">{packages.length} package{packages.length!==1?'s':''}</span>
          <button className="btn primary" onClick={resetForm}><IPlus/> New Package</button>
        </div>
      </div>

      <div className="pkg-layout">

        {/* ── Left: Package List ── */}
        <div className="pkg-list">
          <div className="card" style={{overflow:'hidden'}}>
            <div className="pkg-list-head row">
              <div style={{width:26}} />
              <div className="tbl-th" style={{flex:1,paddingLeft:6}}>Package</div>
              <div className="tbl-th" style={{width:96}}>Menu ID</div>
              <div className="tbl-th" style={{width:96}}>Modifiers</div>
              <div className="tbl-th" style={{width:80}}>Status</div>
              <div className="tbl-th" style={{width:72,textAlign:'right'}}>Actions</div>
            </div>

            {sorted.length === 0 && (
              <div style={{padding:'48px 20px',textAlign:'center',color:'var(--fg3)',fontSize:13}}>
                No packages yet — use the form on the right to add one.
              </div>
            )}

            {sorted.map(pkg => (
              <div key={pkg.id} className={`pkg-row ${editingId===pkg.id ? 'selected' : ''}`} onClick={() => editPkg(pkg)}>
                <div className="pkg-grip"><GripIcon/></div>
                <div className="pkg-info" style={{flex:1}}>
                  <div className="pkg-row-name">{pkg.name}</div>
                  {pkg.description && <div className="pkg-row-desc">{pkg.description}</div>}
                </div>
                <div style={{width:96,flexShrink:0}}>
                  <span className="pill"><span className="mono" style={{fontSize:11}}>{pkg.krypton_menu_id}</span></span>
                </div>
                <div style={{width:96,flexShrink:0}}>
                  <span className="pill blue">{pkg.modifiers.length} item{pkg.modifiers.length!==1?'s':''}</span>
                </div>
                <div style={{width:80,flexShrink:0}} onClick={e=>{e.stopPropagation();toggleActive(pkg.id);}}>
                  <span className={`pill ${pkg.is_active?'green':'red'}`} style={{cursor:'pointer'}} title="Click to toggle">
                    <span style={{width:5,height:5,borderRadius:'50%',background:'currentColor',display:'inline-block',flexShrink:0}}/>
                    {pkg.is_active?'Active':'Inactive'}
                  </span>
                </div>
                <div className="row g4" style={{width:72,flexShrink:0,justifyContent:'flex-end'}} onClick={e=>e.stopPropagation()}>
                  <button className="btn ghost icon-only sm" title="Edit" onClick={()=>editPkg(pkg)}><IPencil/></button>
                  <button className="btn ghost icon-only sm" title="Delete" style={{color:'var(--red)'}} onClick={()=>setConfirm(pkg)}><ITrash/></button>
                </div>
              </div>
            ))}
          </div>

          {/* Quick-tip */}
          <div style={{marginTop:10,display:'flex',alignItems:'center',gap:7,color:'var(--fg3)',fontSize:11.5,padding:'0 4px'}}>
            <IInfo/>
            <span>Click a row to load it into the form panel. Click the status badge to toggle active/inactive inline.</span>
          </div>
        </div>

        {/* ── Right: Form Panel ── */}
        <div className="pkg-panel">
          <div className="card">
            <div className="pkg-panel-head">
              <div className="row g8">
                {editingId
                  ? <><IPencil/><span className="pkg-panel-title" style={{color:'var(--accent)'}}>Editing: {form.name||'…'}</span></>
                  : <><IPlus/><span className="pkg-panel-title">New Package</span></>
                }
              </div>
              {editingId && (
                <button className="btn ghost sm" onClick={resetForm} title="Clear to create new"><IReset/> New</button>
              )}
            </div>

            <div className="pkg-panel-body">
              <Field label="Package Name *" hint="Displayed on POS and staff screens">
                <input className="form-input" placeholder="e.g. Set Meal A"
                  value={form.name} onChange={e => setForm(f=>({...f,name:e.target.value}))}/>
              </Field>

              <Field label="Description" hint="Optional — guest-facing description of what's included">
                <textarea className="form-textarea" rows={2}
                  placeholder="A short description of what this package includes…"
                  value={form.description} onChange={e => setForm(f=>({...f,description:e.target.value}))}/>
              </Field>

              <Field label="Package Menu *" hint="The POS menu entry that represents this bundle (by Krypton ID)">
                <select className="form-select" value={form.krypton_menu_id}
                  onChange={e => setForm(f=>({...f,krypton_menu_id:Number(e.target.value)}))}>
                  <option value={0} disabled>Select menu entry…</option>
                  {MENU_OPTS.map(m => <option key={m.id} value={m.id}>{m.name} (ID: {m.id})</option>)}
                </select>
              </Field>

              <Field label="Display Order" hint="Lower numbers appear first on the tablet">
                <input className="form-input" type="number" min={0} style={{width:96}}
                  value={form.sort_order} onChange={e => setForm(f=>({...f,sort_order:Number(e.target.value)}))}/>
              </Field>

              {/* Modifier Picker */}
              <div>
                <div className="row" style={{marginBottom:6,justifyContent:'space-between',alignItems:'center'}}>
                  <label className="form-label" style={{margin:0}}>Modifier Items</label>
                  {form.modifier_ids.length > 0 &&
                    <span className="pill accent">{form.modifier_ids.length} selected</span>}
                </div>

                {/* Selected chips */}
                {form.modifier_ids.length > 0 ? (
                  <div className="mod-chips" style={{marginBottom:8}}>
                    {selectedMods.map(m => (
                      <div key={m.id} className="mod-chip">
                        {m.name}
                        <span className="mod-chip-x" onClick={() => removeMod(m.id)}>✕</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div style={{marginBottom:8}}>
                    <span className="mod-empty">No modifiers selected.</span>
                  </div>
                )}

                {/* Search input */}
                <div className="row g6" style={{background:'var(--bg0)',border:'1px solid var(--bdr2)',borderRadius:'var(--r-m)',padding:'0 8px',marginBottom:4}}>
                  <ISearch/>
                  <input
                    style={{flex:1,background:'transparent',border:0,outline:'none',color:'var(--fg0)',font:'13px var(--font-s)',height:30,paddingLeft:2}}
                    placeholder="Search by name, ID or receipt code…"
                    value={modSearch} onChange={e => setModSearch(e.target.value)}/>
                  {modSearch && <span style={{cursor:'pointer',color:'var(--fg3)',fontSize:12}} onClick={()=>setModSearch('')}>✕</span>}
                </div>

                {/* Scrollable checkbox list */}
                <div className="mod-scroll">
                  {filteredMods.length === 0 && (
                    <div style={{padding:'14px 10px',textAlign:'center',fontSize:12,color:'var(--fg3)'}}>No matches</div>
                  )}
                  {filteredMods.map(m => (
                    <div key={m.id} className="mod-item"
                      onClick={() => toggleMod(m.id, !form.modifier_ids.includes(m.id))}>
                      <input type="checkbox" readOnly
                        checked={form.modifier_ids.includes(m.id)} onChange={()=>{}}/>
                      <div>
                        <span className="mod-name">{m.name}</span>
                        <span className="mod-sub">#{m.id}</span>
                        {m.receipt_name && <span className="mod-sub"> · {m.receipt_name}</span>}
                      </div>
                    </div>
                  ))}
                </div>
                <div className="form-hint" style={{marginTop:5}}>
                  Selected items appear as guest-selectable choices when ordering this package.
                </div>
              </div>

              {/* Active toggle */}
              <div className="row g10" style={{background:'var(--bg3)',borderRadius:'var(--r-m)',padding:'10px 12px'}}>
                <Toggle value={form.is_active} onChange={v => setForm(f=>({...f,is_active:v}))}/>
                <div>
                  <div style={{fontSize:12,fontWeight:600,fontFamily:'var(--font-d)',color:'var(--fg0)'}}>
                    {form.is_active ? 'Active' : 'Inactive'}
                  </div>
                  <div style={{fontSize:11,color:'var(--fg2)'}}>
                    {form.is_active ? 'Visible on all ordering devices' : 'Hidden from ordering devices'}
                  </div>
                </div>
              </div>
            </div>

            <div className="pkg-panel-foot">
              <button className="btn primary" style={{flex:1}} onClick={savePkg} disabled={!canSave}>
                {editingId ? 'Update Package' : 'Create Package'}
              </button>
              <button className="btn" onClick={resetForm} title="Reset form"><IReset/></button>
            </div>
          </div>
        </div>
      </div>

      <Confirm
        open={!!confirm}
        title="Delete Package?"
        message={`"${confirm?.name}" and all its modifier mappings will be permanently removed. Active ordering sessions are unaffected.`}
        onConfirm={() => deletePkg(confirm)}
        onCancel={() => setConfirm(null)}
      />
    </div>
  );
}

// ── CATEGORIES PAGE ───────────────────────────────────────────────────────────
function toSlug(name) {
  return name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
const EMPTY_CAT = { name:'', slug:'', sort_order:0, is_active:true };

function CatModal({ open, cat, onClose, onSave }) {
  const [form, setForm]           = useState({...EMPTY_CAT});
  const [slugManual, setSlugManual] = useState(false);

  useEffect(() => {
    if (open) {
      setForm(cat ? {...cat} : {...EMPTY_CAT});
      setSlugManual(!!cat);
    }
  }, [open, cat]);

  function setName(v) {
    setForm(f => ({...f, name:v, slug: slugManual ? f.slug : toSlug(v)}));
  }

  if (!open) return null;
  return (
    <div className="modal-wrap" onClick={onClose}>
      <div className="modal" onClick={e => e.stopPropagation()}>
        <div className="modal-head">
          <div className="modal-title">{cat ? 'Edit Category' : 'New Category'}</div>
          <button className="btn ghost icon-only sm" onClick={onClose}>✕</button>
        </div>
        <div className="modal-body">
          <Field label="Name *">
            <input className="form-input" placeholder="e.g. Grilled Meats"
              value={form.name} onChange={e => setName(e.target.value)}/>
          </Field>
          <Field label="Slug" hint="Auto-generated from name if blank. Override only when needed for API integrations.">
            <input className="form-input" style={{fontFamily:'var(--font-m)',fontSize:12}}
              placeholder="e.g. grilled-meats"
              value={form.slug}
              onChange={e => {setSlugManual(true); setForm(f=>({...f,slug:e.target.value}));}}/>
          </Field>
          <Field label="Sort Order" hint="Lower numbers appear first in the tablet tab bar">
            <input className="form-input" type="number" min={0} style={{width:100}}
              value={form.sort_order} onChange={e => setForm(f=>({...f,sort_order:Number(e.target.value)}))}/>
          </Field>
          <div className="row g10" style={{background:'var(--bg3)',borderRadius:'var(--r-m)',padding:'10px 12px'}}>
            <Toggle value={form.is_active} onChange={v => setForm(f=>({...f,is_active:v}))}/>
            <div>
              <div style={{fontSize:12,fontWeight:600,fontFamily:'var(--font-d)',color:'var(--fg0)'}}>
                {form.is_active ? 'Active' : 'Inactive'}
              </div>
              <div style={{fontSize:11,color:'var(--fg2)'}}>
                {form.is_active ? 'Tab visible on ordering tablets' : 'Tab hidden from ordering tablets'}
              </div>
            </div>
          </div>
        </div>
        <div className="modal-foot">
          <button className="btn" onClick={onClose}>Cancel</button>
          <button className="btn primary" onClick={() => onSave(form)} disabled={!form.name.trim()}>
            {cat ? 'Save Changes' : 'Create'}
          </button>
        </div>
      </div>
    </div>
  );
}

function CategoriesPage() {
  const [cats, setCats]           = useState(INIT_CATS);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingCat, setEditingCat] = useState(null);
  const [confirm, setConfirm]     = useState(null);
  const nextId = useRef(200);

  const sorted = [...cats].sort((a,b) => a.sort_order - b.sort_order);

  function openCreate() { setEditingCat(null); setModalOpen(true); }
  function openEdit(cat) { setEditingCat(cat); setModalOpen(true); }

  function saveCat(form) {
    if (editingCat) {
      setCats(cs => cs.map(c => c.id===editingCat.id ? {...c, ...form} : c));
    } else {
      setCats(cs => [...cs, {id: nextId.current++, ...form}]);
    }
    setModalOpen(false);
  }

  function deleteCat(cat) {
    setCats(cs => cs.filter(c => c.id !== cat.id));
    setConfirm(null);
  }

  function toggleActive(id) {
    setCats(cs => cs.map(c => c.id===id ? {...c, is_active:!c.is_active} : c));
  }

  return (
    <div className="content">
      <div className="page-head">
        <div>
          <div className="page-title">Tablet Categories</div>
          <div className="page-sub">Control the category tabs shown on ordering tablets · falls back to POS defaults if empty</div>
        </div>
        <div className="row g8">
          <span className="pill">{cats.length} categor{cats.length!==1?'ies':'y'}</span>
          <button className="btn primary" onClick={openCreate}><IPlus/> New Category</button>
        </div>
      </div>

      <div className="card" style={{overflow:'hidden', marginBottom:10}}>
        {/* Header */}
        <div className="cat-head row">
          <div style={{width:13,flexShrink:0}} />
          <div className="tbl-th" style={{width:26,textAlign:'center',marginLeft:6}}>#</div>
          <div className="tbl-th" style={{flex:1,paddingLeft:12}}>Category</div>
          <div className="tbl-th" style={{width:190}}>Slug</div>
          <div className="tbl-th" style={{width:80}}>Visible</div>
          <div className="tbl-th" style={{width:72,textAlign:'right'}}>Actions</div>
        </div>

        {sorted.length === 0 && (
          <div style={{padding:'40px 20px',textAlign:'center',color:'var(--fg3)',fontSize:13}}>
            No categories. Tablets will use built-in POS defaults.
          </div>
        )}

        {sorted.map(cat => (
          <div key={cat.id} className="cat-row">
            <div className="cat-grip"><GripIcon/></div>
            <div className="cat-ord">{cat.sort_order}</div>
            <div style={{flex:1,paddingLeft:12}}>
              <div className="cat-name">{cat.name}</div>
              <div className="cat-slug">{cat.slug || <span style={{color:'var(--fg3)',fontStyle:'italic'}}>auto-generated</span>}</div>
            </div>
            <div style={{width:190,flexShrink:0}}>
              <span className="mono" style={{fontSize:11,color:'var(--fg2)'}}>{cat.slug||'—'}</span>
            </div>
            <div style={{width:80,flexShrink:0}}>
              <Toggle value={cat.is_active} onChange={() => toggleActive(cat.id)}/>
            </div>
            <div className="row g4" style={{width:72,flexShrink:0,justifyContent:'flex-end'}}>
              <button className="btn ghost icon-only sm" title="Edit" onClick={() => openEdit(cat)}><IPencil/></button>
              <button className="btn ghost icon-only sm" title="Delete" style={{color:'var(--red)'}} onClick={() => setConfirm(cat)}><ITrash/></button>
            </div>
          </div>
        ))}
      </div>

      <div style={{display:'flex',alignItems:'center',gap:7,color:'var(--fg3)',fontSize:11.5,padding:'0 4px'}}>
        <IInfo/>
        <span>Toggle the switch inline to show/hide a category instantly. Use the ⠿ handle as a visual sort reference — update Sort Order in the edit dialog to reorder.</span>
      </div>

      <CatModal open={modalOpen} cat={editingCat} onClose={() => setModalOpen(false)} onSave={saveCat}/>
      <Confirm
        open={!!confirm}
        title="Delete Category?"
        message={`"${confirm?.name}" and all its menu assignments will be removed. Tablets will fall back to the next configured category.`}
        onConfirm={() => deleteCat(confirm)}
        onCancel={() => setConfirm(null)}
      />
    </div>
  );
}

// ── GUIDE PAGE ────────────────────────────────────────────────────────────────
function GuidePage() {
  return (
    <div className="content">
      <div className="page-head">
        <div>
          <div className="page-title">Operation Guide</div>
          <div className="page-sub">How to use the Packages and Tablet Categories interfaces</div>
        </div>
      </div>

      {/* ── Packages ── */}
      <div className="sec-title">Packages Interface</div>

      <div className="card guide-card">
        <div className="guide-h"><IPkg/> Creating a Package</div>
        <div className="guide-step"><div className="step-num">1</div><p className="guide-p">Click <strong>New Package</strong> (top-right) or the <strong>↺ Reset</strong> button on the form panel. The right-side panel clears to a blank state, ready for input.</p></div>
        <div className="guide-step"><div className="step-num">2</div><p className="guide-p">Enter a <strong>Package Name</strong> (e.g. "Set Meal A") and an optional <strong>Description</strong> that guests see during ordering.</p></div>
        <div className="guide-step"><div className="step-num">3</div><p className="guide-p">Select the <strong>Package Menu</strong> — the specific POS entry (Krypton Menu ID) that this bundle maps to. This must match the POS item exactly.</p></div>
        <div className="guide-step"><div className="step-num">4</div><p className="guide-p">In <strong>Modifier Items</strong>, use the search box to find items, then check each one that guests can choose within this package. Selected items appear as <span style={{color:'var(--accent)'}}>amber chips</span> above the list — click <strong>✕</strong> on a chip to remove it.</p></div>
        <div className="guide-step"><div className="step-num">5</div><p className="guide-p">Set the <strong>Display Order</strong> (lower = first on tablet) and toggle <strong>Active</strong> to make the package visible on ordering devices.</p></div>
        <div className="guide-step"><div className="step-num">6</div><p className="guide-p">Click <strong>Create Package</strong>. The new entry immediately appears in the left-side list.</p></div>
        <div className="guide-tip"><IInfo/><p className="guide-tip-text">The <strong>Create Package</strong> button stays disabled until a Name and Package Menu are both filled in — required fields are marked with <strong>*</strong>.</p></div>
      </div>

      <div className="card guide-card">
        <div className="guide-h"><IPencil/> Editing a Package</div>
        <div className="guide-step"><div className="step-num">1</div><p className="guide-p">Click any row in the package list. The row highlights in amber and the form panel populates with its current values.</p></div>
        <div className="guide-step"><div className="step-num">2</div><p className="guide-p">The panel header shows <em style={{color:'var(--accent)'}}>"Editing: {'{Name}'}"</em> to confirm edit mode. Make your changes.</p></div>
        <div className="guide-step"><div className="step-num">3</div><p className="guide-p">Click <strong>Update Package</strong> to save. To discard and start a new package instead, click <strong>↺ New</strong> in the panel header.</p></div>
        <div className="guide-tip"><IInfo/><p className="guide-tip-text">The <strong>status badge</strong> (Active/Inactive) in each list row is clickable — toggle a package on/off without opening the form.</p></div>
      </div>

      <div className="card guide-card">
        <div className="guide-h"><ITrash/> Deleting a Package</div>
        <div className="guide-step"><div className="step-num">1</div><p className="guide-p">Click the <strong>trash icon</strong> on the package row. A confirmation dialog appears with the package name.</p></div>
        <div className="guide-step"><div className="step-num">2</div><p className="guide-p">Click <strong>Delete</strong> to permanently remove the package and all its modifier mappings. Active ordering sessions in progress are <strong>not</strong> interrupted.</p></div>
      </div>

      {/* ── Categories ── */}
      <div className="sec-title" style={{marginTop:20}}>Tablet Categories Interface</div>

      <div className="card guide-card">
        <div className="guide-h"><IList/> Managing Categories</div>
        <div className="guide-step"><div className="step-num">1</div><p className="guide-p">Categories are the <strong>tabs</strong> guests browse on the ordering tablet. Each row shows a drag handle <span style={{fontFamily:'var(--font-m)'}}>⠿</span>, sort number, name, slug, and a live visibility toggle.</p></div>
        <div className="guide-step"><div className="step-num">2</div><p className="guide-p">Flip the <strong>Visible toggle</strong> in the row to show or hide a category instantly — no modal needed. Changes take effect on the next tablet menu refresh.</p></div>
        <div className="guide-step"><div className="step-num">3</div><p className="guide-p">Click <strong>New Category</strong> to open the creation modal. Fill in the name; the slug auto-generates from the name (you can override it). Assign a sort order and set it Active.</p></div>
        <div className="guide-step"><div className="step-num">4</div><p className="guide-p">Click the <strong>pencil icon</strong> to reopen the modal for full edits — name, slug override, sort order, or active state all changeable there.</p></div>
        <div className="guide-tip"><IInfo/><p className="guide-tip-text">If <strong>no categories</strong> are configured, ordering tablets fall back to the built-in POS category list automatically.</p></div>
        <div className="guide-tip" style={{marginTop:6}}><IInfo/><p className="guide-tip-text">The <strong>Slug</strong> is the URL-safe identifier. Only override it if you have an API integration that references category slugs directly.</p></div>
      </div>
    </div>
  );
}

// ── PROMPT PAGE ───────────────────────────────────────────────────────────────
function CopyBtn({ text }) {
  const [copied, setCopied] = useState(false);
  function copy() {
    navigator.clipboard.writeText(text).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }
  return (
    <span className={`prompt-copy ${copied ? 'copied' : ''}`} onClick={copy}>
      {copied ? <><ICheck/> Copied</> : <><ICopy/> Copy</>}
    </span>
  );
}

const PROMPT_TEXT = `You are working on the woosoo-nexus Laravel + Inertia + Vue 3 codebase.
Implement the following UI/UX redesigns for two admin pages.
Follow the existing design system strictly: Raleway (headings/labels),
Kanit (body), JetBrains Mono (IDs/numbers), Woosoo amber (#F6B56D) as accent.
Use only existing shadcn-vue components already imported in the project.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FILE 1: resources/js/pages/Packages/Index.vue
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CURRENT PROBLEM:
The form sits permanently above a flat table. Creating and editing share
the same top-of-page form, making it hard to see the full package list
while editing. Modifier selection is a tiny scrollbox with no visual
feedback on what is selected.

REDESIGN — Layout:
Replace the stacked form+table with a side-by-side split:
  Left panel  (flex:1)   — enhanced package list (rows, not just table)
  Right panel (340px)    — sticky create/edit form panel (always visible)

REDESIGN — Left panel (package list):
- Keep existing <Table> but add a drag-handle icon column (visual only,
  no actual drag needed — just <GripVertical class="h-3.5 w-3.5" />).
- Columns: [handle] | Package (name + description) | Menu ID | Modifiers | Status | Actions
- "Menu ID" cell: render value in a <Badge variant="outline"> with font-mono text.
- "Modifiers" cell: render modifier count as <Badge variant="secondary">
  e.g. "4 items".
- "Status" cell: make the badge CLICKABLE — clicking it calls
  router.put(route('packages.toggle', item.id)) to toggle is_active
  without opening the form. Add title="Click to toggle".
- Each row: clicking anywhere except Actions column calls editPackage(item).
- Highlight the editing row: add a CSS class "ring-1 ring-amber-400/40
  bg-amber-400/5" when item.id === editingId.

REDESIGN — Right panel (form):
- Wrap the form in a <Card> with sticky top-0 positioning.
- Card header: show "New Package" (Plus icon) OR
  "Editing: {form.name}" (Pencil icon, text in amber) depending on editingId.
- Add a "↺ New" ghost button in the header when in edit mode (calls resetForm).
- Form fields stay the same (name, description, krypton_menu_id select,
  sort_order, modifiers, is_active) but reorganised:
    1. Name
    2. Description
    3. Package Menu (select)
    4. Display Order (width: w-24)
    5. Modifier Items (search + list + chips — see below)
    6. Active toggle row
- Modifier Items UX — replace the old checkbox-only list with:
    a. A row header showing "Modifier Items" label + selected-count badge.
    b. Selected items rendered as removable amber chip badges ABOVE the list:
         <Badge variant="outline" class="...amber styles...">
           {name} <button @click="removeMod(id)">✕</button>
         </Badge>
       (show "No modifiers selected." in muted text when empty)
    c. Search <Input> below the chips (v-model="modifierSearch").
    d. Scrollable checkbox list (max-h-40 overflow-y-auto) below search —
       filtered by modifierSearch.
- Submit + Reset buttons in card footer (outside the scroll area).
- Disable submit button when name or krypton_menu_id is empty.

NEW ROUTE needed (add to routes/web.php + PackageController):
  Route::put('packages/{package}/toggle', [PackageController::class, 'toggle'])
       ->name('packages.toggle');
  // Controller method: toggles is_active, returns back()

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FILE 2: resources/js/pages/tablet-categories/IndexTabletCategories.vue
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CURRENT PROBLEM:
The table is minimal — no visual drag-order cues, no inline toggle,
and the slug field gives no feedback during creation.

REDESIGN — Table enhancements:
- Add a <GripVertical> handle as first column (visual only).
- Add a sort-order number badge (<Badge variant="outline" class="font-mono
  text-xs">) as second column.
- Name column: show name as primary text + slug as secondary text
  in font-mono text-xs text-muted-foreground below it.
- REMOVE the standalone Slug column (it's now shown under the name).
- Add an inline "Visible" column with a <Switch> component wired to
  router.put(route('tablet-categories.toggle', cat.id)).
  No modal needed — the switch fires immediately.
- Actions column: keep Edit (pencil) and Delete (trash) icon buttons.

REDESIGN — Create/Edit dialogs:
- In the Create dialog, add live slug preview:
    - Below the Slug input, show a <p class="text-xs text-muted-foreground">
      that displays: "Will be stored as: {slugPreview}" where slugPreview
      is a computed ref that auto-generates the slug from the name input
      (name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/, ''))
      unless the slug field has been manually edited.
    - Use a watch on createForm.name to keep slugPreview in sync when
      the slug field hasn't been touched.
- Wrap the Active toggle in the same row layout as the Packages form
  (toggle + label with description text).
- Dialog widths: max-w-md is fine.

NEW ROUTE needed:
  Route::put('tablet-categories/{category}/toggle',
             [TabletCategoryController::class, 'toggle'])
       ->name('tablet-categories.toggle');

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SHARED CSS — append to resources/css/app.css
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

/* Packages split layout */
.pkg-split { display: flex; gap: 1rem; align-items: flex-start; }
.pkg-split-list  { flex: 1; min-width: 0; }
.pkg-split-panel { width: 340px; flex-shrink: 0; position: sticky; top: 1rem; }

/* Modifier chip */
.mod-chip {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 6px 2px 9px; border-radius: 9999px;
  border: 1px solid hsl(var(--border)); font-size: 11px; font-weight: 600;
  background: hsl(var(--muted)); color: hsl(var(--foreground));
  font-family: var(--font-sans);
}

/* Editing-row highlight */
.pkg-row-editing {
  --tw-ring-color: rgb(246 181 109 / 0.4);
  ring: 1px;
  background: rgb(246 181 109 / 0.05);
}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TESTING CHECKLIST
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Packages:
  [ ] Create a new package — appears in list, form resets
  [ ] Click a list row — form loads correct values, row highlights amber
  [ ] Update a package — list reflects new values
  [ ] Click status badge — toggles active/inactive without opening form
  [ ] Delete a package — removed from list, confirm dialog shown first
  [ ] Modifier chips: add via checkbox, remove via ✕ on chip
  [ ] Modifier search: filters list, clear X resets it
  [ ] Create Package button disabled when name/menu empty

Tablet Categories:
  [ ] Create category — slug auto-generates from name, shown in preview
  [ ] Inline Visible toggle — fires toggle route, no modal
  [ ] Edit category — all fields editable, slug can be overridden
  [ ] Delete category — confirmation dialog shown, then removed
  [ ] Empty state — shows fallback message`;

function PromptPage() {
  const sections = [
    {
      tag: 'FILE 1', label: 'Packages / Index.vue',
      file: 'resources/js/pages/Packages/Index.vue',
      changes: [
        { type:'new',  text:'Split layout: left = package list, right = sticky form panel (always visible)' },
        { type:'new',  text:'Package list rows: drag handle icon, clickable row loads form, highlighted amber when selected' },
        { type:'new',  text:'Status badge in list is clickable — toggles active/inactive inline via packages.toggle route' },
        { type:'new',  text:'Modifier picker: selected items shown as removable amber chips above the scrollable checkbox list' },
        { type:'new',  text:'Search input filters modifier list; clear button resets search' },
        { type:'new',  text:'Submit button disabled until name + package menu are filled' },
        { type:'fix',  text:'Form no longer displaces the list — always side by side' },
        { type:'fix',  text:'Modifier CSV in table replaced with "N items" count badge' },
      ],
    },
    {
      tag: 'FILE 2', label: 'tablet-categories / IndexTabletCategories.vue',
      file: 'resources/js/pages/tablet-categories/IndexTabletCategories.vue',
      changes: [
        { type:'new',  text:'Drag handle column (visual) and sort-order number badge added to each row' },
        { type:'new',  text:'Inline Visible toggle switch per row — fires toggle route immediately, no modal needed' },
        { type:'new',  text:'Name + slug shown stacked in one column (slug as mono secondary text, separate Slug column removed)' },
        { type:'new',  text:'Create dialog: slug auto-generates from name with live preview below the slug field' },
        { type:'fix',  text:'Active state no longer requires opening the edit modal' },
        { type:'fix',  text:'Slug feedback — user knows what value will be saved before submitting' },
      ],
    },
    {
      tag: 'ROUTE',  label: 'New toggle routes',
      file: 'routes/web.php + Controllers',
      changes: [
        { type:'new',  text:'Route::put packages/{package}/toggle → PackageController@toggle' },
        { type:'new',  text:'Route::put tablet-categories/{category}/toggle → TabletCategoryController@toggle' },
      ],
    },
    {
      tag: 'CSS',    label: 'app.css additions',
      file: 'resources/css/app.css',
      changes: [
        { type:'new',  text:'.pkg-split, .pkg-split-list, .pkg-split-panel layout utilities' },
        { type:'new',  text:'.mod-chip amber chip style for selected modifiers' },
        { type:'new',  text:'.pkg-row-editing highlight for the currently selected row' },
      ],
    },
  ];

  return (
    <div className="content">
      <div className="page-head">
        <div>
          <div className="page-title">Developer Prompt</div>
          <div className="page-sub">Implementation spec — copy the full prompt into Claude Code or your preferred AI tool</div>
        </div>
        <CopyBtn text={PROMPT_TEXT}/>
      </div>

      {/* Change summary */}
      <div className="sec-title">Change Summary</div>
      {sections.map((sec,i) => (
        <div key={i} className="card prompt-card" style={{marginBottom:12}}>
          <div className="row g10" style={{marginBottom:14}}>
            <span className="prompt-tag">{sec.tag}</span>
            <span style={{fontFamily:'var(--font-d)',fontSize:13,fontWeight:700,color:'var(--fg0)'}}>{sec.label}</span>
            <span style={{marginLeft:'auto'}} className="prompt-file">{sec.file}</span>
          </div>
          <div>
            {sec.changes.map((c,j) => (
              <div key={j} className="change-row">
                <span className={`change-label ${c.type==='fix'?'fix':''}`}>{c.type==='new'?'NEW':'FIX'}</span>
                <span className="change-text">{c.text}</span>
              </div>
            ))}
          </div>
        </div>
      ))}

      {/* Full prompt */}
      <div className="sec-title" style={{marginTop:20}}>Full Implementation Prompt</div>
      <div className="card prompt-card">
        <div className="row" style={{justifyContent:'space-between',alignItems:'center',marginBottom:12}}>
          <span style={{fontFamily:'var(--font-d)',fontSize:12,fontWeight:700,color:'var(--fg1)',letterSpacing:'.04em',textTransform:'uppercase'}}>Paste this into Claude Code</span>
          <CopyBtn text={PROMPT_TEXT}/>
        </div>
        <pre className="prompt-code" style={{maxHeight:480,overflowY:'auto'}}>{PROMPT_TEXT}</pre>
      </div>
    </div>
  );
}

// ── APP ───────────────────────────────────────────────────────────────────────
function App() {
  const [page, setPage]   = useState('packages');
  const [theme, setTheme] = useState(() => localStorage.getItem('nexus-theme') || 'dark');

  function toggleTheme() {
    const next = theme === 'dark' ? 'light' : 'dark';
    setTheme(next);
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('nexus-theme', next);
  }

  const breadcrumb = {
    packages:   'Admin → Packages',
    categories: 'Admin → Tablet Categories',
    guide:      'Operation Guide',
    prompt:     'Developer Prompt',
  }[page];

  return (
    <div className="app">
      <Sidebar page={page} onPage={setPage}/>
      <div className="main">
        <div className="topbar">
          <div className="row g8">
            <span style={{fontSize:11.5,color:'var(--fg3)',fontFamily:'var(--font-d)',letterSpacing:'.08em',textTransform:'uppercase',fontWeight:700}}>
              {breadcrumb}
            </span>
          </div>
          <div className="row g8">
            <span style={{fontSize:11,color:'var(--fg3)',fontFamily:'var(--font-s)'}}>Woosoo Nexus — UI/UX v2</span>
            <button className="btn ghost sm" onClick={toggleTheme}>
              {theme === 'dark' ? '☀ Light' : '◑ Dark'}
            </button>
          </div>
        </div>
        {page === 'packages'   && <PackagesPage/>}
        {page === 'categories' && <CategoriesPage/>}
        {page === 'guide'      && <GuidePage/>}
        {page === 'prompt'     && <PromptPage/>}
      </div>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
