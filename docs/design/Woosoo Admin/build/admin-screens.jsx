const { useState } = React;
const { Icon, Pill, Btn } = window;

// ─── Toast system ─────────────────────────────────────────────────────────────
const TOAST_CFG = {
  success: { color:'var(--green)', bg:'var(--greenm)', border:'var(--greenb)', icon:'check'   },
  error:   { color:'var(--red)',   bg:'var(--redm)',   border:'var(--redb)',   icon:'warning' },
  warning: { color:'var(--amber)', bg:'var(--amberm)', border:'var(--amberb)', icon:'warning' },
  info:    { color:'var(--blue)',  bg:'var(--bluem)',  border:'var(--blueb)',  icon:'bell'    },
};
const ToastItem = ({ toast, onRemove }) => {
  const [vis, setVis] = React.useState(false);
  React.useEffect(()=>{
    const r = requestAnimationFrame(()=>setVis(true));
    const t1 = setTimeout(()=>setVis(false), 3000);
    const t2 = setTimeout(onRemove, 3400);
    return ()=>{ cancelAnimationFrame(r); clearTimeout(t1); clearTimeout(t2); };
  }, []);
  const c = TOAST_CFG[toast.variant] || TOAST_CFG.success;
  return (
    <div style={{display:'flex',alignItems:'center',gap:10,padding:'10px 14px',borderRadius:'var(--r-l)',background:'var(--bg2)',
      border:`1px solid ${c.border}`,boxShadow:'0 4px 24px rgba(0,0,0,.3)',minWidth:260,maxWidth:380,pointerEvents:'all',
      transform:vis?'translateX(0)':'translateX(110%)',opacity:vis?1:0,
      transition:'transform .3s cubic-bezier(.22,.68,0,1.1), opacity .3s ease'}}>
      <div style={{width:26,height:26,borderRadius:'50%',background:c.bg,border:`1px solid ${c.border}`,display:'flex',alignItems:'center',justifyContent:'center',color:c.color,flexShrink:0}}>
        <Icon n={c.icon} size={13}/>
      </div>
      <span style={{flex:1,fontSize:12.5,color:'var(--fg0)',fontFamily:'var(--font-d)',fontWeight:500}}>{toast.msg}</span>
      <div onClick={onRemove} style={{cursor:'pointer',color:'var(--fg3)',flexShrink:0,marginLeft:4}}><Icon n="close" size={11}/></div>
    </div>
  );
};
const ToastContainer = () => {
  const [toasts, setToasts] = React.useState([]);
  React.useEffect(()=>{
    window.nexusToast = (msg, variant='success') => {
      const id = Date.now()+Math.random();
      setToasts(prev=>[...prev.slice(-4),{id,msg,variant}]);
    };
    return ()=>{ window.nexusToast = null; };
  }, []);
  const remove = id => setToasts(prev=>prev.filter(t=>t.id!==id));
  if(!toasts.length) return null;
  return (
    <div style={{position:'fixed',bottom:24,right:24,zIndex:9999,display:'flex',flexDirection:'column',gap:8,pointerEvents:'none',alignItems:'flex-end'}}>
      {toasts.map(t=><ToastItem key={t.id} toast={t} onRemove={()=>remove(t.id)}/>)}
    </div>
  );
};

// ═══════════════ REVERB SCREEN ══════════════════════════

const ReverbScreen = () => {
  const [svcStatus, setSvcStatus] = useState('running');
  const [confirm, setConfirm] = useState(null);

  const handleAction = action => {
    if(action==='stop')    setSvcStatus('stopped');
    if(action==='start')   setSvcStatus('running');
    if(action==='restart') setSvcStatus('running');
    setConfirm(null);
    if(window.nexusToast) window.nexusToast(
      action==='stop'?'Reverb server stopped':action==='start'?'Reverb server started':'Reverb server restarted',
      action==='stop'?'warning':'success'
    );
  };

  return (
    <div className="content">
      <div className="card" style={{marginBottom:14,padding:'22px 24px'}}>
        <div style={{display:'flex',alignItems:'center',gap:20}}>
          <div style={{width:52,height:52,borderRadius:'50%',background:svcStatus==='running'?'var(--greenm)':'var(--redm)',border:`2px solid ${svcStatus==='running'?'var(--greenb)':'var(--redb)'}`,display:'flex',alignItems:'center',justifyContent:'center',color:svcStatus==='running'?'var(--green)':'var(--red)',flexShrink:0}}>
            <Icon n="reverb" size={22}/>
          </div>
          <div style={{flex:1}}>
            <div className="section-title" style={{marginBottom:5}}>Reverb WebSocket Server</div>
            <Pill label={svcStatus==='running'?'Running':'Stopped'} variant={svcStatus==='running'?'green':'red'} dot={svcStatus==='running'}/>
          </div>
          <div style={{display:'flex',gap:8}}>
            {svcStatus==='stopped'
              ? <Btn primary onClick={()=>handleAction('start')}><Icon n="check" size={13}/>Start Server</Btn>
              : <Btn ghost onClick={()=>setConfirm('stop')} style={{color:'var(--red)'}}><Icon n="close" size={13}/>Stop</Btn>}
            <Btn onClick={()=>setConfirm('restart')}><Icon n="refresh" size={13}/>Restart</Btn>
          </div>
        </div>
      </div>

      <div className="kpi-grid" style={{marginBottom:14}}>
        {[
          {label:'PID',               value:'12847',  sub:'Process ID'},
          {label:'Uptime',            value:'4h 32m', sub:'Since last restart'},
          {label:'Connected Clients', value:'24',     sub:'Active WebSocket connections'},
          {label:'Active Channels',   value:'18',     sub:'Broadcast channels'},
        ].map(s=>(
          <div key={s.label} className="card kpi-card">
            <div className="kpi-label">{s.label}</div>
            <div className="kpi-val" style={{fontSize:26}}>{s.value}</div>
            <div className="kpi-delta">{s.sub}</div>
          </div>
        ))}
      </div>

      <div className="card">
        <div className="card-head"><span className="card-head-label">Server Log</span><Btn sm ghost>View Full Log</Btn></div>
        <div style={{padding:'12px 18px',fontFamily:'var(--font-m)',fontSize:12,display:'flex',flexDirection:'column',gap:0}}>
          {[
            {t:'20:01:12',l:'INFO', msg:'New connection: client_id=c_8a21f  channel=orders.T-04'},
            {t:'20:01:08',l:'INFO', msg:'Broadcast: App\\Events\\OrderUpdated → orders channel'},
            {t:'20:00:54',l:'INFO', msg:'New connection: client_id=c_7b34d  channel=orders.T-01'},
            {t:'20:00:48',l:'WARN', msg:'Client disconnected unexpectedly: client_id=c_5c12a'},
            {t:'20:00:31',l:'INFO', msg:'Broadcast: App\\Events\\PrintJobCreated → print channel'},
            {t:'20:00:10',l:'INFO', msg:'Heartbeat: 24 active connections, 18 channels'},
          ].map((row,i)=>(
            <div key={i} style={{display:'flex',gap:12,padding:'7px 0',borderBottom:i<5?'1px solid var(--bdr1)':'none'}}>
              <span style={{color:'var(--fg3)',flexShrink:0}}>{row.t}</span>
              <span style={{color:row.l==='WARN'?'var(--amber)':'var(--blue)',width:36,flexShrink:0}}>{row.l}</span>
              <span style={{color:'var(--fg1)'}}>{row.msg}</span>
            </div>
          ))}
        </div>
      </div>

      {confirm && (
        <div className="modal-wrap" onClick={()=>setConfirm(null)}>
          <div className="modal" style={{width:420}} onClick={e=>e.stopPropagation()}>
            <div style={{padding:'20px 22px'}}>
              <div style={{fontFamily:'var(--font-d)',fontSize:18,fontWeight:800,color:'var(--red)',marginBottom:8,display:'flex',alignItems:'center',gap:8}}>
                <Icon n="warning" size={18}/>{confirm==='stop'?'Stop Reverb Server?':'Restart Reverb Server?'}
              </div>
              <div style={{fontSize:13.5,color:'var(--fg1)',lineHeight:1.6}}>
                {confirm==='stop'
                  ? 'Stopping will disconnect all 24 active clients. Tablet real-time updates will be interrupted.'
                  : 'All 24 clients will briefly disconnect. They will reconnect automatically once the server restarts.'}
              </div>
            </div>
            <div style={{padding:'0 22px 18px',display:'flex',gap:8,justifyContent:'flex-end'}}>
              <Btn ghost onClick={()=>setConfirm(null)}>Cancel</Btn>
              <Btn danger onClick={()=>handleAction(confirm)}><Icon n={confirm==='stop'?'close':'refresh'} size={13}/>{confirm==='stop'?'Stop Server':'Restart Server'}</Btn>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// ═══════════════ CONFIGURATION HUB ══════════════════════

const ConfigurationScreen = ({ onNav }) => {
  const tiles = [
    { title:'POS Connection',    desc:'Configure and test Krypton DB connection', icon:'pos',      page:'pos',          status:{label:'Connected',   variant:'green'} },
    { title:'App Settings',      desc:'Theme, alerts, pagination preferences',   icon:'settings', page:'settings',     status:null },
    { title:'Package Configs',   desc:'Admin-managed tablet package tiers',      icon:'package',  page:'packages',     status:null },
    { title:'Tablet Categories', desc:'Reorder categories on the tablet UI',     icon:'category', page:'tablet-cats',  status:null },
    { title:'Branches',          desc:'Multi-branch management',                 icon:'branch',   page:'branches',     status:{label:'1 branch',    variant:''} },
    { title:'Database & Cache',  desc:'MySQL, Redis and queue health status',    icon:'server',   page:'monitoring',   status:{label:'All healthy', variant:'green'} },
  ];
  return (
    <div className="content">
      <div style={{display:'grid',gridTemplateColumns:'repeat(3,1fr)',gap:14}}>
        {tiles.map(tile=>(
          <div key={tile.title} className="card" style={{padding:'22px',cursor:'pointer',transition:'border-color .15s'}}
            onClick={()=>onNav&&onNav(tile.page)}
            onMouseEnter={e=>e.currentTarget.style.borderColor='var(--accb)'}
            onMouseLeave={e=>e.currentTarget.style.borderColor=''}>
            <div style={{display:'flex',alignItems:'flex-start',justifyContent:'space-between',marginBottom:14}}>
              <div style={{width:42,height:42,borderRadius:'var(--r-l)',background:'var(--accm)',border:'1px solid var(--accb)',display:'flex',alignItems:'center',justifyContent:'center',color:'var(--accent)'}}>
                <Icon n={tile.icon} size={20}/>
              </div>
              {tile.status && <Pill label={tile.status.label} variant={tile.status.variant}/>}
            </div>
            <div style={{fontFamily:'var(--font-d)',fontSize:15,fontWeight:800,color:'var(--fg0)',marginBottom:5}}>{tile.title}</div>
            <div style={{fontSize:12.5,color:'var(--fg2)',lineHeight:1.6}}>{tile.desc}</div>
            <div style={{marginTop:14,fontSize:11,color:'var(--accent)',fontFamily:'var(--font-d)',fontWeight:700,letterSpacing:'0.04em'}}>Configure →</div>
          </div>
        ))}
      </div>
    </div>
  );
};

// ── Sound utility ──────────────────────────────────────────────────────────
const playChime = () => {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const note = (freq, t, dur) => {
      const osc = ctx.createOscillator(), g = ctx.createGain();
      osc.connect(g); g.connect(ctx.destination);
      osc.type = 'sine'; osc.frequency.value = freq;
      g.gain.setValueAtTime(0, t);
      g.gain.linearRampToValueAtTime(0.22, t+0.012);
      g.gain.exponentialRampToValueAtTime(0.001, t+dur);
      osc.start(t); osc.stop(t+dur);
    };
    const now = ctx.currentTime;
    note(880,    now,      0.38);  // A5
    note(1108.7, now+0.13, 0.46);  // C#6
    note(1318.5, now+0.26, 0.55);  // E6
  } catch(e) {}
};
window.woosooChime = () => { if(localStorage.getItem('nexus-sound')==='true') playChime(); };

// ═══════════════ SETTINGS SCREEN ════════════════════════

const SettingToggle = ({ on, onChange }) => (
  <div className={`perm-switch ${on?'on':'off'}`} onClick={onChange}><div className="perm-knob"/></div>
);

const SettingsScreen = ({ theme: themeProp = 'dark', onThemeChange }) => {
  const [form, setForm] = useState({
    theme: themeProp, itemsPerPage:25,
    emailNotif:true, orderAlerts:true,
    soundAlerts: localStorage.getItem('nexus-sound')==='true',
    posName:'Krypton POS', apiUrl:'https://nexus.woosoo.ph/api', wsUrl:'wss://nexus.woosoo.ph:8080',
  });
  React.useEffect(()=>{ setForm(f=>({...f, theme: themeProp})); }, [themeProp]);
  const set = (k, v) => {
    setForm(f=>({...f,[k]:v}));
    if(k==='theme' && onThemeChange) onThemeChange(v);
    if(k==='soundAlerts') localStorage.setItem('nexus-sound', String(v));
  };
  const [saved, setSaved] = useState(false);

  const SettingRow = ({label, desc, children}) => (
    <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'12px 0',borderBottom:'1px solid var(--bdr1)'}}>
      <div><div style={{fontSize:13,fontWeight:600,color:'var(--fg0)',fontFamily:'var(--font-d)',marginBottom:2}}>{label}</div><div style={{fontSize:12,color:'var(--fg2)'}}>{desc}</div></div>
      {children}
    </div>
  );

  return (
    <div className="content">
      <div style={{maxWidth:640}}>
        <div className="card" style={{marginBottom:14,overflow:'hidden'}}>
          <div className="card-head"><span className="card-head-label">Interface</span></div>
          <div style={{padding:'4px 20px 16px'}}>
            <div style={{padding:'12px 0',borderBottom:'1px solid var(--bdr1)'}}>
              <div style={{fontSize:10,letterSpacing:'0.12em',textTransform:'uppercase',color:'var(--fg2)',fontWeight:700,fontFamily:'var(--font-d)',marginBottom:8}}>Theme</div>
              <div style={{display:'flex',gap:8}}>
                {['light','dark','system'].map(t=>(
                  <div key={t} onClick={()=>set('theme',t)} style={{flex:1,textAlign:'center',padding:'7px',borderRadius:'var(--r-m)',cursor:'pointer',fontSize:12,fontWeight:700,fontFamily:'var(--font-d)',textTransform:'capitalize',background:form.theme===t?'var(--accm)':'var(--bg3)',border:`1px solid ${form.theme===t?'var(--accb)':'var(--bdr2)'}`,color:form.theme===t?'var(--accent)':'var(--fg2)'}}>
                    {t}
                  </div>
                ))}
              </div>
            </div>
            <div style={{padding:'12px 0'}}>
              <div style={{fontSize:10,letterSpacing:'0.12em',textTransform:'uppercase',color:'var(--fg2)',fontWeight:700,fontFamily:'var(--font-d)',marginBottom:8}}>Items Per Page</div>
              <div style={{display:'flex',gap:8}}>
                {[10,25,50,100].map(n=>(
                  <div key={n} onClick={()=>set('itemsPerPage',n)} style={{padding:'6px 18px',borderRadius:'var(--r-m)',cursor:'pointer',fontSize:12,fontWeight:700,fontFamily:'var(--font-m)',background:form.itemsPerPage===n?'var(--accm)':'var(--bg3)',border:`1px solid ${form.itemsPerPage===n?'var(--accb)':'var(--bdr2)'}`,color:form.itemsPerPage===n?'var(--accent)':'var(--fg2)'}}>
                    {n}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="card" style={{marginBottom:14,overflow:'hidden'}}>
          <div className="card-head"><span className="card-head-label">Notifications</span></div>
          <div style={{padding:'0 20px'}}>
            {[
              {key:'emailNotif',  label:'Email Notifications', desc:'Send order and system alerts via email'},
              {key:'orderAlerts', label:'Order Alerts',         desc:'In-app alerts for new and urgent orders'},
              {key:'soundAlerts', label:'Sound Alerts',         desc:'Audio chime on each new order received'},
            ].map(s=>(
              <SettingRow key={s.key} label={s.label} desc={s.desc}>
                <div style={{display:'flex',alignItems:'center',gap:10}}>
                  {s.key==='soundAlerts' && form.soundAlerts && (
                    <Btn sm ghost onClick={playChime} style={{fontSize:11,gap:5}}>
                      <Icon n="bell" size={11}/>Test
                    </Btn>
                  )}
                  <SettingToggle on={form[s.key]} onChange={()=>set(s.key,!form[s.key])}/>
                </div>
              </SettingRow>
            ))}
          </div>
        </div>

        <div className="card" style={{marginBottom:20,overflow:'hidden'}}>
          <div className="card-head"><span className="card-head-label">Integrations</span></div>
          <div style={{padding:'12px 20px',display:'flex',flexDirection:'column',gap:14}}>
            {[
              {key:'posName', label:'POS System Name',  placeholder:'e.g. Krypton POS'},
              {key:'apiUrl',  label:'API Base URL',      placeholder:'https://nexus.woosoo.ph/api'},
              {key:'wsUrl',   label:'WebSocket URL',     placeholder:'wss://nexus.woosoo.ph:8080'},
            ].map(f=>(
              <div key={f.key}>
                <div className="form-label">{f.label}</div>
                <input className="form-input" value={form[f.key]} onChange={e=>set(f.key,e.target.value)} placeholder={f.placeholder}/>
              </div>
            ))}
          </div>
        </div>

        <div style={{display:'flex',gap:8,justifyContent:'flex-end',alignItems:'center'}}>
          {saved && <span style={{fontSize:12,color:'var(--green)',fontFamily:'var(--font-d)',fontWeight:600,display:'inline-flex',gap:5,alignItems:'center'}}><Icon n="check" size={12}/>Saved</span>}
          <Btn ghost>Reset to Defaults</Btn>
          <Btn primary onClick={()=>{setSaved(true);setTimeout(()=>setSaved(false),2500);if(window.nexusToast)window.nexusToast('Settings saved successfully','success');}}><Icon n="check" size={13}/>Save Settings</Btn>
        </div>
      </div>
    </div>
  );
};

// ═══════════════ TABLET CATEGORIES SCREEN ═══════════════

const TABLET_CATS_DATA = [
  { id:'tc1', name:'Meats',      slug:'meats',     menuCount:7, sort:1, active:true  },
  { id:'tc2', name:'Sides',      slug:'sides',     menuCount:5, sort:2, active:true  },
  { id:'tc3', name:'Extras',     slug:'extras',    menuCount:3, sort:3, active:true  },
  { id:'tc4', name:'Sets',       slug:'sets',      menuCount:3, sort:4, active:true  },
  { id:'tc5', name:'Beverages',  slug:'beverages', menuCount:0, sort:5, active:false },
];
const CAT_MENUS = {
  tc1: ['Plain Samgyupsal','Yangyeom Samgyupsal','Woosamgyup','Moksal','Beef Bulgogi','Hyangcho Woosamgyup','Korean Chili Samgyupsal'],
  tc2: ['Traditional Kimchi','Korean Pickled Radish','Gamja Jorim','Gyeran Jjim','Banchan Set'],
  tc3: ['Woosoo Cheese','Golden Mushroom Beef Roll','Dubu Ganjeong'],
  tc4: ['Classic Feast Set','Noble Set','Royal Set'],
  tc5: [],
};

const TabletCategoriesScreen = () => {
  const [sel, setSel] = useState(TABLET_CATS_DATA[0]);
  return (
    <div className="content">
      <div className="page-head" style={{marginBottom:14}}>
        <div className="page-sub">Drag to reorder · changes reflect on tablets immediately</div>
        <Btn primary><Icon n="plus" size={13}/>New Category</Btn>
      </div>
      <div style={{display:'grid',gridTemplateColumns:'300px 1fr',gap:14,alignItems:'start'}}>
        <div className="card" style={{overflow:'hidden'}}>
          <div className="card-head">
            <span className="card-head-label">Categories</span>
            <Pill label={`${TABLET_CATS_DATA.filter(c=>c.active).length} active`} variant="green"/>
          </div>
          {TABLET_CATS_DATA.map((cat,i)=>(
            <div key={cat.id} onClick={()=>setSel(cat)}
              style={{display:'flex',alignItems:'center',gap:10,padding:'11px 16px',borderBottom:i<TABLET_CATS_DATA.length-1?'1px solid var(--bdr1)':'none',cursor:'pointer',background:sel?.id===cat.id?'var(--bg3)':'transparent',transition:'background .1s'}}>
              <div style={{display:'flex',flexDirection:'column',gap:2.5,cursor:'grab',color:'var(--fg3)',flexShrink:0}}>
                {[0,1,2].map(d=><div key={d} style={{width:12,height:1.5,background:'currentColor',borderRadius:1}}/>)}
              </div>
              <div style={{width:24,height:24,borderRadius:'var(--r-s)',background:'var(--accm)',border:'1px solid var(--accb)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:10,fontFamily:'var(--font-m)',color:'var(--accent)',fontWeight:600,flexShrink:0}}>{cat.sort}</div>
              <div style={{flex:1}}>
                <div style={{fontSize:13,fontWeight:700,color:'var(--fg0)',fontFamily:'var(--font-d)'}}>{cat.name}</div>
                <div style={{fontSize:11,color:'var(--fg3)',fontFamily:'var(--font-m)'}}>{cat.slug} · {cat.menuCount} items</div>
              </div>
              <Pill label={cat.active?'Active':'Off'} variant={cat.active?'green':'gray'}/>
            </div>
          ))}
        </div>

        {sel && (
          <div className="card" style={{overflow:'hidden'}}>
            <div className="card-head">
              <span className="card-head-label">{sel.name}</span>
              <div style={{display:'flex',gap:8}}>
                <Pill label={sel.active?'Active':'Inactive'} variant={sel.active?'green':'gray'} dot={sel.active}/>
                <Btn sm ghost><Icon n="edit" size={12}/>Edit</Btn>
              </div>
            </div>
            <div style={{padding:'14px 18px',borderBottom:'1px solid var(--bdr1)',display:'grid',gridTemplateColumns:'repeat(3,1fr)',gap:10}}>
              {[{label:'Slug',val:sel.slug},{label:'Sort Order',val:`#${sel.sort}`},{label:'Menu Items',val:sel.menuCount}].map(m=>(
                <div key={m.label}><div className="meta-label">{m.label}</div><div style={{fontFamily:'var(--font-m)',fontSize:15,color:'var(--fg0)',marginTop:2}}>{m.val}</div></div>
              ))}
            </div>
            <div>
              <div style={{padding:'12px 18px 6px',fontSize:10,letterSpacing:'0.12em',textTransform:'uppercase',color:'var(--fg2)',fontWeight:700,fontFamily:'var(--font-d)'}}>Attached Menus</div>
              {(CAT_MENUS[sel.id]||[]).map(menu=>(
                <div key={menu} style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'8px 18px',borderTop:'1px solid var(--bdr1)'}}>
                  <span style={{fontSize:13,color:'var(--fg1)'}}>{menu}</span>
                  <div style={{display:'flex',gap:6,alignItems:'center'}}>
                    <Pill label="Featured" variant="accent"/>
                    <Btn sm ghost style={{color:'var(--red)'}}><Icon n="close" size={11}/>Detach</Btn>
                  </div>
                </div>
              ))}
              {(CAT_MENUS[sel.id]||[]).length===0 && <div style={{padding:'20px 18px',textAlign:'center',fontSize:12,color:'var(--fg3)'}}>No menus attached</div>}
              <div style={{padding:'12px 18px',borderTop:'1px solid var(--bdr1)'}}><Btn sm ghost><Icon n="plus" size={12}/>Attach Menu</Btn></div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

// ═══════════════ ROLES SCREEN ════════════════════════════

const ROLES_DATA = [
  { id:'r1', name:'Manager',         guard:'web', perms:24, users:3, desc:'Full access to operations and catalog'    },
  { id:'r2', name:'Server',          guard:'web', perms:8,  users:2, desc:'View orders and manage devices'           },
  { id:'r3', name:'Kitchen',         guard:'web', perms:4,  users:2, desc:'Live orders view only'                    },
  { id:'r4', name:'System Operator', guard:'web', perms:32, users:1, desc:'Full system access including monitoring'  },
];

const RolesScreen = () => (
  <div className="content">
    <div className="page-head" style={{marginBottom:14}}>
      <div style={{display:'flex',gap:8}}>
        <Pill label={`${ROLES_DATA.length} roles`} variant="accent"/>
        <Pill label="8 users total"/>
      </div>
      <Btn primary><Icon n="plus" size={13}/>Create Role</Btn>
    </div>
    <div className="card" style={{overflow:'hidden'}}>
      <table className="tbl">
        <thead><tr><th>Role</th><th>Description</th><th>Guard</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
        <tbody>
          {ROLES_DATA.map(r=>(
            <tr key={r.id}>
              <td style={{fontWeight:700,fontFamily:'var(--font-d)',fontSize:13.5}}>{r.name}</td>
              <td><span style={{fontSize:12.5,color:'var(--fg2)'}}>{r.desc}</span></td>
              <td><span style={{fontFamily:'var(--font-m)',fontSize:12,color:'var(--fg2)'}}>{r.guard}</span></td>
              <td><span style={{fontFamily:'var(--font-m)',fontSize:13}}>{r.perms} perms</span></td>
              <td><Pill label={`${r.users} user${r.users!==1?'s':''}`} variant={r.users>0?'green':'gray'}/></td>
              <td style={{textAlign:'right'}}>
                <div style={{display:'flex',gap:6,justifyContent:'flex-end'}}>
                  <Btn sm ghost><Icon n="lock" size={12}/>Permissions</Btn>
                  <Btn sm ghost><Icon n="edit" size={12}/>Edit</Btn>
                  {r.users===0 && <Btn sm ghost style={{color:'var(--red)'}}><Icon n="trash" size={12}/></Btn>}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  </div>
);

// ═══════════════ PERMISSIONS SCREEN ═════════════════════

const PERMS_DATA = [
  { id:'p1',  name:'view-dashboard',       guard:'web', roles:['Manager','Server','Kitchen','System Operator'] },
  { id:'p2',  name:'view-orders',          guard:'web', roles:['Manager','Server','Kitchen','System Operator'] },
  { id:'p3',  name:'manage-orders',        guard:'web', roles:['Manager','System Operator']                    },
  { id:'p4',  name:'void-orders',          guard:'web', roles:['Manager','System Operator']                    },
  { id:'p5',  name:'view-menus',           guard:'web', roles:['Manager','Server','System Operator']            },
  { id:'p6',  name:'manage-menus',         guard:'web', roles:['Manager']                                      },
  { id:'p7',  name:'view-devices',         guard:'web', roles:['Manager','Server','System Operator']            },
  { id:'p8',  name:'manage-devices',       guard:'web', roles:['Manager','System Operator']                    },
  { id:'p9',  name:'view-reports',         guard:'web', roles:['Manager','System Operator']                    },
  { id:'p10', name:'manage-users',         guard:'web', roles:['Manager','System Operator']                    },
  { id:'p11', name:'view-monitoring',      guard:'web', roles:['Manager','System Operator']                    },
  { id:'p12', name:'manage-configuration', guard:'web', roles:['System Operator']                              },
];

const PermissionsScreen = () => {
  const [search, setSearch] = useState('');
  const filtered = search ? PERMS_DATA.filter(p=>p.name.includes(search.toLowerCase())) : PERMS_DATA;
  return (
    <div className="content">
      <div className="page-head" style={{marginBottom:14}}>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <div className="search-box" style={{minWidth:260}}>
            <Icon n="search" size={13}/>
            <input placeholder="Search permissions…" value={search} onChange={e=>setSearch(e.target.value)}/>
          </div>
          <Pill label={`${PERMS_DATA.length} total`}/>
        </div>
        <Btn primary><Icon n="plus" size={13}/>Add Permission</Btn>
      </div>
      <div className="card" style={{overflow:'hidden'}}>
        <table className="tbl">
          <thead><tr><th>Permission</th><th>Guard</th><th>Assigned Roles</th><th></th></tr></thead>
          <tbody>
            {filtered.map(p=>(
              <tr key={p.id}>
                <td><span style={{fontFamily:'var(--font-m)',fontSize:12.5}}>{p.name}</span></td>
                <td><span style={{fontFamily:'var(--font-m)',fontSize:12,color:'var(--fg2)'}}>{p.guard}</span></td>
                <td>
                  <div style={{display:'flex',gap:5,flexWrap:'wrap'}}>
                    {p.roles.map(r=><Pill key={r} label={r} variant="accent"/>)}
                  </div>
                </td>
                <td style={{textAlign:'right'}}>
                  <div style={{display:'flex',gap:6,justifyContent:'flex-end'}}>
                    <Btn sm ghost><Icon n="edit" size={12}/>Edit</Btn>
                    <Btn sm ghost style={{color:'var(--red)'}}><Icon n="trash" size={12}/></Btn>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

// ═══════════════ EXPORTS ════════════════════════════════

Object.assign(window, {
  ToastContainer,
  ReverbScreen, ConfigurationScreen, SettingsScreen,
  TabletCategoriesScreen, RolesScreen, PermissionsScreen,
});
