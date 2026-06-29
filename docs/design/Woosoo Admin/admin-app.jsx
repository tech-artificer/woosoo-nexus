const { useState, useMemo } = React;
const {
  Icon, Pill, Btn,
  Dashboard, OrdersScreen, MenuScreen, DevicesScreen,
  SESSIONS, PKG_PRICES,
} = window;
// New screens loaded from admin-screens.jsx
const getScreen = name => window[name];

// ═══════════════ POS DATA ═══════════════════════════════

const POS_TABLES = [
  { id:'T-01', guests:6, pkg:'Royal Banquet',   status:'open',   since:'6:52 PM', elapsed:88 },
  { id:'T-02', guests:3, pkg:'Noble Selection', status:'open',   since:'7:10 PM', elapsed:70 },
  { id:'T-03', guests:2, pkg:'Royal Banquet',   status:'open',   since:'7:20 PM', elapsed:60 },
  { id:'T-04', guests:4, pkg:'Noble Selection', status:'open',   since:'7:28 PM', elapsed:52 },
  { id:'T-05', guests:4, pkg:'Classic Feast',   status:'open',   since:'6:45 PM', elapsed:95 },
  { id:'T-06', guests:2, pkg:'Classic Feast',   status:'open',   since:'7:24 PM', elapsed:56 },
  { id:'T-07', guests:5, pkg:'Noble Selection', status:'open',   since:'7:05 PM', elapsed:75 },
  { id:'T-08', guests:3, pkg:'Classic Feast',   status:'open',   since:'7:32 PM', elapsed:48 },
  { id:'T-09', guests:0, pkg:null,              status:'closed', since:null,       elapsed:0  },
  { id:'T-10', guests:0, pkg:null,              status:'closed', since:null,       elapsed:0  },
  { id:'T-11', guests:6, pkg:'Royal Banquet',   status:'open',   since:'6:30 PM', elapsed:110},
  { id:'T-12', guests:2, pkg:'Classic Feast',   status:'open',   since:'7:40 PM', elapsed:40 },
];

// ═══════════════ POS SCREEN ═════════════════════════════

const POSTablePanel = ({ table, onClose }) => {
  const total = table.guests * (PKG_PRICES[table.pkg] || 0);
  return (
    <div className="overlay" onClick={onClose}>
      <div className="drawer" onClick={e=>e.stopPropagation()}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 18px',borderBottom:'1px solid var(--bdr2)'}}>
          <div>
            <div style={{fontFamily:'var(--font-m)',fontSize:12,color:'var(--accent)',marginBottom:2}}>{table.id}</div>
            <div style={{fontFamily:'var(--font-d)',fontSize:20,fontWeight:800}}>{table.guests} guests · {table.pkg}</div>
          </div>
          <Btn ghost iconOnly onClick={onClose}><Icon n="close" size={14}/></Btn>
        </div>
        <div style={{padding:'14px 18px',borderBottom:'1px solid var(--bdr1)',background:'var(--bg0)'}}>
          {[
            {label:'Package',    val:table.pkg},
            {label:'Guests',     val:`${table.guests} pax`},
            {label:'Seated at',  val:table.since},
            {label:'Duration',   val:`${table.elapsed} min`},
            {label:'Session total', val:`₱${total.toLocaleString()}`},
          ].map(r=>(
            <div key={r.label} style={{display:'flex',justifyContent:'space-between',padding:'7px 0',borderBottom:'1px solid var(--bdr1)'}}>
              <span style={{fontSize:12,color:'var(--fg2)',fontFamily:'var(--font-d)',letterSpacing:'0.06em',textTransform:'uppercase',fontWeight:700}}>{r.label}</span>
              <span style={{fontSize:13,color:'var(--fg0)',fontFamily:'var(--font-m)'}}>{r.val}</span>
            </div>
          ))}
        </div>
        <div style={{padding:'14px 18px',flex:1}}>
          <div style={{fontSize:10.5,letterSpacing:'0.12em',textTransform:'uppercase',color:'var(--fg2)',fontWeight:700,fontFamily:'var(--font-d)',marginBottom:10}}>Batch Orders at {table.id}</div>
          {SESSIONS.filter(s=>s.table===table.id).map(s=>(
            <div key={s.id} style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'9px 12px',background:'var(--bg3)',borderRadius:'var(--r-m)',border:'1px solid var(--bdr1)',marginBottom:6}}>
              <div>
                <div style={{fontFamily:'var(--font-m)',fontSize:12,color:'var(--accent)'}}>{s.id}</div>
                <div style={{fontSize:12,color:'var(--fg2)',marginTop:2}}>{s.time}</div>
              </div>
              <Pill label={s.status} variant={s.status==='confirmed'?'accent':'green'}/>
            </div>
          ))}
          {SESSIONS.filter(s=>s.table===table.id).length === 0 && (
            <div style={{padding:'20px 0',textAlign:'center',fontSize:12,color:'var(--fg3)'}}>No sessions recorded</div>
          )}
        </div>
        <div style={{padding:'12px 18px',borderTop:'1px solid var(--bdr1)',display:'flex',gap:8}}>
          <Btn danger sm><Icon n="close" size={12}/>Void Session</Btn>
          <Btn primary style={{flex:1,justifyContent:'center'}}><Icon n="check" size={13}/>Mark Paid</Btn>
        </div>
      </div>
    </div>
  );
};

const POSScreen = () => {
  const [selTable, setSelTable] = useState(null);
  const openCount   = POS_TABLES.filter(t=>t.status==='open').length;
  const totalGuests = POS_TABLES.filter(t=>t.status==='open').reduce((s,t)=>s+t.guests,0);

  return (
    <div className="content">
      {selTable && <POSTablePanel table={selTable} onClose={()=>setSelTable(null)}/>}
      <div className="page-head" style={{marginBottom:14}}>
        <div>
          <div style={{display:'flex',gap:8,marginBottom:4}}>
            <Pill label={`${openCount} open tables`} variant="accent" dot={true}/>
            <Pill label={`${totalGuests} guests dining`} variant="green" dot={false}/>
          </div>
          <div className="page-sub">Krypton POS · Live table status</div>
        </div>
        <div style={{display:'flex',gap:8}}>
          <Btn><Icon n="refresh" size={13}/>Sync POS</Btn>
          <Btn primary><Icon n="plus" size={13}/>New Order</Btn>
        </div>
      </div>

      {/* Terminal selector */}
      <div style={{display:'flex',gap:8,marginBottom:14}}>
        {['Krypton Main','Krypton Bar'].map((t,i)=>(
          <div key={t} style={{padding:'6px 14px',borderRadius:'var(--r-m)',border:`1px solid ${i===0?'var(--accb)':'var(--bdr2)'}`,background:i===0?'var(--accm)':'var(--bg2)',cursor:'pointer',fontSize:12,fontWeight:700,fontFamily:'var(--font-d)',color:i===0?'var(--accent)':'var(--fg2)',display:'flex',alignItems:'center',gap:6}}>
            <Icon n="server" size={12}/>{t}
          </div>
        ))}
      </div>

      {/* Table grid */}
      <div className="pos-table-grid">
        {POS_TABLES.map(t=>(
          <div key={t.id}
            className={`pos-table-card ${t.status}`}
            onClick={()=>t.status==='open'&&setSelTable(t)}>
            <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:8}}>
              <span style={{fontFamily:'var(--font-d)',fontSize:16,fontWeight:800,color:'var(--fg0)'}}>{t.id}</span>
              {t.status==='open'
                ? <Pill label="Open" variant="green" dot={true}/>
                : <Pill label="Closed" variant="gray"/>}
            </div>
            {t.status==='open' ? (
              <>
                <div style={{fontSize:12,color:'var(--fg1)',marginBottom:4,fontFamily:'var(--font-d)',fontWeight:600}}>{t.pkg}</div>
                <div style={{fontSize:12,color:'var(--fg2)',marginBottom:8}}>{t.guests} pax · {t.elapsed}m</div>
                <div style={{height:3,background:'var(--bg4)',borderRadius:99}}>
                  <div style={{height:'100%',width:`${Math.min(t.elapsed/90*100,100)}%`,background:t.elapsed>80?'var(--amber)':'var(--accent)',borderRadius:99}}/>
                </div>
              </>
            ) : (
              <div style={{fontSize:12,color:'var(--fg3)',marginTop:8}}>Available</div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
};

// ═══════════════ PACKAGES SCREEN ════════════════════════

const PACKAGES = [
  {
    tier:'Entry Tier', name:'Classic Feast', price:449, tag:'classic',
    meats:['Plain Samgyupsal','Yangyeom Samgyupsal','Moksal','Beef Bulgogi'],
    sides:['Kimchi','Pickled Radish','Gamja Jorim','Gyeran Jjim','Banchan Set'],
    extras:[],
    note:'Unlimited refills. 90-minute dining time.',
  },
  {
    tier:'Mid Tier', name:'Noble Selection', price:499, tag:'noble', featured:true,
    meats:['Plain Samgyupsal','Yangyeom Samgyupsal','Moksal','Beef Bulgogi','Hyangcho Woosamgyup','Korean Chili Samgyupsal'],
    sides:['Kimchi','Pickled Radish','Gamja Jorim','Gyeran Jjim','Banchan Set'],
    extras:['1 Woosoo Cheese'],
    note:'Unlimited refills. 90-minute dining time.',
  },
  {
    tier:'Premium Tier', name:'Royal Banquet', price:549, tag:'royal',
    meats:['Plain Samgyupsal','Yangyeom Samgyupsal','Moksal','Beef Bulgogi','Hyangcho Woosamgyup','Korean Chili Samgyupsal','Woosamgyup'],
    sides:['Kimchi','Pickled Radish','Gamja Jorim','Gyeran Jjim','Banchan Set'],
    extras:['1 Woosoo Cheese','1 Golden Mushroom Beef Roll','1 Dubu Ganjeong'],
    note:'Unlimited refills. 90-minute dining time.',
  },
];

const PackagesScreen = () => {
  const [activeTab, setActiveTab] = useState('packages');
  return (
    <div className="content">
      <div className="page-head" style={{marginBottom:14}}>
        <div>
          <div style={{display:'flex',gap:6,marginBottom:4}}>
            {['packages','configs'].map(t=>(
              <div key={t} onClick={()=>setActiveTab(t)}
                style={{padding:'5px 14px',borderRadius:'var(--r-m)',border:`1px solid ${activeTab===t?'var(--accb)':'var(--bdr2)'}`,background:activeTab===t?'var(--accm)':'transparent',cursor:'pointer',fontSize:12,fontWeight:700,fontFamily:'var(--font-d)',color:activeTab===t?'var(--accent)':'var(--fg2)',textTransform:'capitalize'}}>
                {t==='packages'?'Packages':'Package Configs'}
              </div>
            ))}
          </div>
          <div className="page-sub">{activeTab==='packages'?'3 active packages · All published':'Admin-managed tablet package configurations'}</div>
        </div>
        <Btn primary><Icon n="plus" size={13}/>New Package</Btn>
      </div>

      {activeTab==='packages' && (
        <div className="pkg-grid">
          {PACKAGES.map(pkg=>(
            <div key={pkg.tag} className={`pkg-card${pkg.featured?' featured':''}`}>
              {pkg.featured && (
                <div style={{position:'absolute',top:0,right:0,padding:'4px 14px',background:'var(--accent)',color:'var(--accfg)',fontSize:10,fontWeight:700,fontFamily:'var(--font-d)',letterSpacing:'0.1em',textTransform:'uppercase',borderRadius:'0 var(--r-xl) 0 var(--r-m)'}}>
                  Best Seller
                </div>
              )}
              <div className="pkg-tier">{pkg.tier}</div>
              <div className="pkg-name">{pkg.name}</div>
              <div className="pkg-price">₱{pkg.price}<span className="pkg-per"> / pax</span></div>

              <div style={{height:1,background:'var(--bdr1)',margin:'16px 0'}}/>

              <div style={{marginBottom:12}}>
                <div style={{fontSize:10,letterSpacing:'0.12em',textTransform:'uppercase',color:'var(--fg3)',fontWeight:700,fontFamily:'var(--font-d)',marginBottom:8}}>Meats <span style={{color:'var(--accent)'}}>{pkg.meats.length} cuts</span></div>
                <div style={{display:'flex',flexDirection:'column',gap:5}}>
                  {pkg.meats.map(m=>(
                    <div key={m} style={{display:'flex',alignItems:'center',gap:8,fontSize:12.5,color:'var(--fg1)'}}>
                      <div style={{width:5,height:5,borderRadius:'50%',background:'var(--accent)',flexShrink:0}}/>
                      {m}
                    </div>
                  ))}
                </div>
              </div>

              {pkg.extras.length>0 && (
                <div style={{marginBottom:12}}>
                  <div style={{fontSize:10,letterSpacing:'0.12em',textTransform:'uppercase',color:'var(--fg3)',fontWeight:700,fontFamily:'var(--font-d)',marginBottom:8}}>Add-ons included</div>
                  {pkg.extras.map(e=>(
                    <div key={e} style={{display:'flex',alignItems:'center',gap:8,fontSize:12.5,color:'var(--green)',marginBottom:4}}>
                      <Icon n="check" size={11}/>{e}
                    </div>
                  ))}
                </div>
              )}

              <div style={{marginTop:'auto',paddingTop:12,borderTop:'1px solid var(--bdr1)',display:'flex',gap:8}}>
                <Btn sm ghost style={{flex:1,justifyContent:'center'}}><Icon n="edit" size={12}/>Edit</Btn>
                <Btn sm ghost style={{flex:1,justifyContent:'center'}}><Icon n="eye" size={12}/>Preview</Btn>
              </div>
            </div>
          ))}
        </div>
      )}

      {activeTab==='configs' && (
        <div className="card">
          <div className="card-head"><span className="card-head-label">Package Configs</span><Btn sm primary><Icon n="plus" size={12}/>New Config</Btn></div>
          <table className="tbl">
            <thead><tr><th>Config Name</th><th>Linked Package</th><th>Allowed Menus</th><th>Status</th><th></th></tr></thead>
            <tbody>
              {[
                { name:'Classic Feast — Main Hall',   pkg:'Classic Feast',   menus:12, active:true  },
                { name:'Noble Selection — Main Hall',  pkg:'Noble Selection', menus:18, active:true  },
                { name:'Royal Banquet — VIP Room',     pkg:'Royal Banquet',   menus:22, active:true  },
                { name:'Classic Feast — Terrace',      pkg:'Classic Feast',   menus:10, active:false },
              ].map(c=>(
                <tr key={c.name}>
                  <td style={{fontWeight:600,fontFamily:'var(--font-d)',fontSize:13}}>{c.name}</td>
                  <td><Pill label={c.pkg} variant="accent"/></td>
                  <td><span style={{fontFamily:'var(--font-m)',fontSize:13}}>{c.menus} items</span></td>
                  <td><Pill label={c.active?'Active':'Inactive'} variant={c.active?'green':'gray'} dot={c.active}/></td>
                  <td style={{textAlign:'right'}}><Btn sm ghost><Icon n="edit" size={12}/></Btn></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

// ═══════════════ MONITORING SCREEN ══════════════════════

const MonitoringScreen = () => {
  const [purging, setPurging] = useState(false);
  return (
    <div className="content">
      <div className="page-head" style={{marginBottom:14}}>
        <div><div className="page-sub">System health · Last checked 4s ago</div></div>
        <Btn><Icon n="refresh" size={13}/>Refresh</Btn>
      </div>

      {/* DB + Service health */}
      <div className="health-grid" style={{marginBottom:14}}>
        {[
          { label:'MySQL',     status:'ok',   val:'12ms',  sub:'Primary DB'       },
          { label:'Redis',     status:'ok',   val:'1ms',   sub:'Cache / Queue'    },
          { label:'POS DB',    status:'ok',   val:'28ms',  sub:'Krypton DB'       },
          { label:'Reverb',    status:'ok',   val:'Running',sub:'WebSocket server'},
        ].map(h=>(
          <div key={h.label} className="card health-card">
            <div className="health-status">
              <div className={`health-dot ${h.status}`}/>
              <span style={{fontSize:10,letterSpacing:'0.14em',textTransform:'uppercase',fontWeight:700,fontFamily:'var(--font-d)',color:'var(--fg2)'}}>{h.label}</span>
            </div>
            <div style={{fontFamily:'var(--font-m)',fontSize:22,color:'var(--fg0)',letterSpacing:'-0.02em',marginBottom:2}}>{h.val}</div>
            <div style={{fontSize:11.5,color:'var(--fg2)'}}>{h.sub}</div>
          </div>
        ))}
      </div>

      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,marginBottom:12}}>
        {/* Queue stats */}
        <div className="card">
          <div className="card-head"><span className="card-head-label">Queue Stats</span><Pill label="Healthy" variant="green" dot={true}/></div>
          <div style={{padding:'14px 18px',display:'grid',gridTemplateColumns:'repeat(3,1fr)',gap:8}}>
            {[{label:'Depth',val:'3'},{label:'Failed',val:'0'},{label:'Last Run',val:'2s'}].map(q=>(
              <div key={q.label} style={{textAlign:'center',padding:'12px 8px',background:'var(--bg3)',borderRadius:'var(--r-m)',border:'1px solid var(--bdr1)'}}>
                <div style={{fontFamily:'var(--font-m)',fontSize:24,color:'var(--fg0)',marginBottom:4}}>{q.val}</div>
                <div style={{fontSize:10,color:'var(--fg3)',fontFamily:'var(--font-d)',fontWeight:700,letterSpacing:'0.1em',textTransform:'uppercase'}}>{q.label}</div>
              </div>
            ))}
          </div>
          <div style={{padding:'0 18px 14px',display:'flex',gap:8}}>
            <Btn sm ghost><Icon n="eye" size={12}/>View Jobs</Btn>
            <Btn sm danger><Icon n="trash" size={12}/>Purge Failed</Btn>
          </div>
        </div>

        {/* Print events */}
        <div className="card">
          <div className="card-head"><span className="card-head-label">Print Events</span></div>
          <div style={{padding:'14px 18px',display:'grid',gridTemplateColumns:'repeat(4,1fr)',gap:8}}>
            {[{label:'Pending',val:'2',color:'var(--amber)'},{label:'Reserved',val:'1',color:'var(--blue)'},{label:'Failed',val:'0',color:'var(--red)'},{label:'Total Today',val:'84',color:'var(--fg0)'}].map(p=>(
              <div key={p.label} style={{textAlign:'center',padding:'12px 8px',background:'var(--bg3)',borderRadius:'var(--r-m)',border:'1px solid var(--bdr1)'}}>
                <div style={{fontFamily:'var(--font-m)',fontSize:24,color:p.color,marginBottom:4}}>{p.val}</div>
                <div style={{fontSize:9.5,color:'var(--fg3)',fontFamily:'var(--font-d)',fontWeight:700,letterSpacing:'0.1em',textTransform:'uppercase'}}>{p.label}</div>
              </div>
            ))}
          </div>
          <div style={{padding:'0 18px 14px',display:'flex',gap:8}}>
            <Btn sm ghost><Icon n="print" size={12}/>Print Audit</Btn>
            <Btn sm danger onClick={()=>setPurging(true)}><Icon n="trash" size={12}/>Purge All</Btn>
          </div>
        </div>
      </div>

      {/* Device anomalies */}
      <div className="card" style={{marginBottom:12}}>
        <div className="card-head"><span className="card-head-label">Device Anomalies</span><Pill label="2 issues" variant="amber" dot={true}/></div>
        <table className="tbl">
          <thead><tr><th>Device</th><th>Table</th><th>Issue</th><th>Since</th><th></th></tr></thead>
          <tbody>
            {[
              { dev:'Tablet 07', table:'T-07', issue:'Last ping > 3 min ago',         since:'3 min ago',  severity:'warn'  },
              { dev:'Tablet 08', table:'T-08', issue:'Device offline, battery depleted', since:'41 min ago', severity:'error' },
            ].map(a=>(
              <tr key={a.dev}>
                <td style={{fontWeight:600,fontFamily:'var(--font-d)'}}>{a.dev}</td>
                <td><span style={{fontFamily:'var(--font-m)',fontSize:12}}>{a.table}</span></td>
                <td><span style={{fontSize:12.5,color:a.severity==='error'?'var(--red)':'var(--amber)'}}>{a.issue}</span></td>
                <td><span style={{fontFamily:'var(--font-m)',fontSize:12,color:'var(--fg2)'}}>{a.since}</span></td>
                <td style={{textAlign:'right'}}><Btn sm ghost><Icon n="eye" size={12}/>View</Btn></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Recent failed jobs */}
      <div className="card">
        <div className="card-head"><span className="card-head-label">Recent Failed Jobs</span><Pill label="All clear" variant="green" dot={true}/></div>
        <div style={{padding:'32px',textAlign:'center',color:'var(--fg3)',fontSize:13}}>
          <Icon n="check" size={20}/><div style={{marginTop:8}}>No failed jobs in the last 24 hours</div>
        </div>
      </div>

      {purging && (
        <div className="modal-wrap" onClick={()=>setPurging(false)}>
          <div className="modal" style={{width:440}} onClick={e=>e.stopPropagation()}>
            <div style={{padding:'20px 22px'}}>
              <div style={{fontFamily:'var(--font-d)',fontSize:18,fontWeight:800,color:'var(--red)',marginBottom:8,display:'flex',alignItems:'center',gap:8}}><Icon n="warning" size={18}/>Purge All Print Events?</div>
              <div style={{fontSize:13.5,color:'var(--fg1)',lineHeight:1.6}}>This will permanently delete all pending, reserved, and failed print events. This action cannot be undone.</div>
            </div>
            <div style={{padding:'0 22px 18px',display:'flex',gap:8,justifyContent:'flex-end'}}>
              <Btn ghost onClick={()=>setPurging(false)}>Cancel</Btn>
              <Btn danger onClick={()=>setPurging(false)}><Icon n="trash" size={13}/>Purge All Events</Btn>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// ═══════════════ STAFF / USERS SCREEN ═══════════════════

const STAFF = [
  { id:'s1', initials:'ML', color:'#8b5e3c', name:'Min-jun Lee',   role:'Manager', email:'minjun@woosoo.ph',  lastSeen:'Now',    active:true,  perms:{reports:true, menu:true, staff:true, refunds:true, devices:true}   },
  { id:'s2', initials:'JP', color:'#4a7c59', name:'Ji-yeon Park',  role:'Server',  email:'jiyeon@woosoo.ph',  lastSeen:'2h ago', active:true,  perms:{reports:false,menu:false,staff:false,refunds:false,devices:true}   },
  { id:'s3', initials:'HK', color:'#4a6694', name:'Hyun-soo Kim',  role:'Kitchen', email:'hyunsoo@woosoo.ph', lastSeen:'5m ago', active:true,  perms:{reports:false,menu:false,staff:false,refunds:false,devices:false}  },
  { id:'s4', initials:'SC', color:'#8b4a70', name:'So-ra Choi',    role:'Server',  email:'sora@woosoo.ph',    lastSeen:'3h ago', active:true,  perms:{reports:false,menu:false,staff:false,refunds:false,devices:true}   },
  { id:'s5', initials:'DK', color:'#5c6a3d', name:'Dong-hyun Ko',  role:'Kitchen', email:'donghyun@woosoo.ph',lastSeen:'1h ago', active:true,  perms:{reports:false,menu:false,staff:false,refunds:false,devices:false}  },
  { id:'s6', initials:'YS', color:'#6b3622', name:'Yuna Song',     role:'Manager', email:'yuna@woosoo.ph',    lastSeen:'Off',    active:false, perms:{reports:true, menu:true, staff:false,refunds:true, devices:true}   },
];
const ROLE_COLORS = { Manager:'accent', Server:'blue', Kitchen:'amber' };
const PERM_DEFS = [
  { id:'reports', label:'View Reports',     desc:'Access sales analytics and revenue data' },
  { id:'menu',    label:'Edit Menu',        desc:'Add, modify, or remove menu items' },
  { id:'staff',   label:'Manage Staff',    desc:'Invite and remove team members' },
  { id:'refunds', label:'Process Refunds', desc:'Issue refunds and order adjustments' },
  { id:'devices', label:'Manage Devices',  desc:'Monitor and configure tablet devices' },
];

const PermSwitch = ({ on, onChange }) => (
  <div className={`perm-switch ${on?'on':'off'}`} onClick={onChange}><div className="perm-knob"/></div>
);

const UsersScreen = () => {
  const [sel, setSel]     = useState(STAFF[0]);
  const [perms, setPerms] = useState(()=>Object.fromEntries(STAFF.map(s=>[s.id,{...s.perms}])));
  const toggle = (sid, pid) => setPerms(prev=>({...prev,[sid]:{...prev[sid],[pid]:!prev[sid][pid]}}));

  return (
    <div className="content">
      <div className="page-head" style={{marginBottom:14}}>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <Pill label={`${STAFF.filter(s=>s.active).length} active`} variant="green" dot={true}/>
          <Pill label={`${STAFF.length} total`}/>
        </div>
        <Btn primary><Icon n="plus" size={13}/>Invite User</Btn>
      </div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 340px',gap:14,alignItems:'start'}}>
        <div className="card" style={{overflow:'hidden'}}>
          <div className="card-head">
            <span className="card-head-label">Team Members</span>
            <div className="search-box" style={{minWidth:200}}><Icon n="search" size={13}/><input placeholder="Search…"/></div>
          </div>
          <table className="tbl">
            <thead><tr><th>Name</th><th>Role</th><th>Last Seen</th><th>Status</th><th></th></tr></thead>
            <tbody>
              {STAFF.map(s=>(
                <tr key={s.id} onClick={()=>setSel(s)} style={{cursor:'pointer',background:sel?.id===s.id?'var(--bg3)':''}}>
                  <td>
                    <div style={{display:'flex',alignItems:'center',gap:10}}>
                      <div className="staff-av" style={{background:s.color}}>{s.initials}</div>
                      <div>
                        <div style={{fontSize:13,fontWeight:700,color:'var(--fg0)',fontFamily:'var(--font-d)'}}>{s.name}</div>
                        <div style={{fontSize:11.5,color:'var(--fg2)'}}>{s.email}</div>
                      </div>
                    </div>
                  </td>
                  <td><Pill label={s.role} variant={ROLE_COLORS[s.role]||''}/></td>
                  <td><span style={{fontFamily:'var(--font-m)',fontSize:12,color:'var(--fg2)'}}>{s.lastSeen}</span></td>
                  <td><Pill label={s.active?'Active':'Inactive'} variant={s.active?'green':'red'} dot={true}/></td>
                  <td><Btn sm ghost><Icon n="edit" size={12}/></Btn></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {sel && (
          <div className="card" style={{overflow:'hidden'}}>
            <div style={{padding:'14px 16px',borderBottom:'1px solid var(--bdr1)'}}>
              <div style={{display:'flex',alignItems:'center',gap:10,marginBottom:10}}>
                <div className="staff-av" style={{background:sel.color,width:38,height:38,fontSize:15}}>{sel.initials}</div>
                <div>
                  <div style={{fontSize:14,fontWeight:700,color:'var(--fg0)',fontFamily:'var(--font-d)'}}>{sel.name}</div>
                  <div style={{fontSize:12,color:'var(--fg2)'}}>{sel.email}</div>
                </div>
              </div>
              <div style={{display:'flex',gap:6}}>
                <Pill label={sel.role} variant={ROLE_COLORS[sel.role]||''}/>
                <Pill label={sel.active?'Active':'Inactive'} variant={sel.active?'green':'red'} dot={true}/>
              </div>
            </div>
            <div style={{padding:'12px 16px'}}>
              <div className="section-title" style={{marginBottom:10}}>Permissions</div>
              {PERM_DEFS.map(p=>(
                <div key={p.id} className="perm-row">
                  <div>
                    <div style={{fontSize:13,fontWeight:600,color:'var(--fg0)',marginBottom:1,fontFamily:'var(--font-d)'}}>{p.label}</div>
                    <div style={{fontSize:11.5,color:'var(--fg2)'}}>{p.desc}</div>
                  </div>
                  <PermSwitch on={perms[sel.id]?.[p.id]} onChange={()=>toggle(sel.id,p.id)}/>
                </div>
              ))}
            </div>
            <div style={{padding:'10px 16px',borderTop:'1px solid var(--bdr1)',display:'flex',gap:8}}>
              <Btn sm ghost style={{color:'var(--red)'}}><Icon n="trash" size={12}/>Remove</Btn>
              <Btn sm primary style={{marginLeft:'auto'}}>Save Changes</Btn>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

// ═══════════════ REPORTS SCREEN ═════════════════════════

const WEEKLY = [
  {d:'Mon',v:28400},{d:'Tue',v:31200},{d:'Wed',v:29800},
  {d:'Thu',v:34600},{d:'Fri',v:48200},{d:'Sat',v:52400},{d:'Sun',v:34200},
];
const MONTHLY = [
  {d:'Apr 7',v:198000},{d:'Apr 14',v:214000},{d:'Apr 21',v:232000},
  {d:'Apr 28',v:208000},{d:'May 5',v:248000},{d:'May 12',v:276000},{d:'May 18',v:258400},
];
const TOP_ITEMS = [
  {name:'Yangyeom Samgyupsal', count:486, pct:100},
  {name:'Plain Samgyupsal',    count:412, pct:85},
  {name:'Woosamgyup',          count:368, pct:76},
  {name:'Beef Bulgogi',        count:334, pct:69},
  {name:'Moksal',              count:298, pct:61},
  {name:'Hyangcho Woosamgyup', count:241, pct:50},
];
const PKG_MIX = [
  {label:'Noble Selection', value:48, color:'#F6B56D'},
  {label:'Classic Feast',   value:32, color:'#c99540'},
  {label:'Royal Banquet',   value:20, color:'#7a9bc4'},
];

const LineChart = ({ data }) => {
  const W=480, H=110, PX=32, PY=12;
  const maxV = Math.max(...data.map(d=>d.v),1);
  const px = i => PX+(i/(data.length-1))*(W-PX*2);
  const py = v => PY+(1-(v/maxV))*(H-PY*2);
  const pts = data.map((d,i)=>`${px(i)},${py(d.v)}`).join(' ');
  const area = `${px(0)},${H-PY} ${pts} ${px(data.length-1)},${H-PY}`;
  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{width:'100%',height:H}}>
      <defs>
        <linearGradient id="lgr" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#F6B56D" stopOpacity="0.3"/>
          <stop offset="100%" stopColor="#F6B56D" stopOpacity="0.02"/>
        </linearGradient>
      </defs>
      {[0.25,0.5,0.75,1].map(p=>(
        <line key={p} x1={PX} x2={W-PX} y1={py(maxV*p)} y2={py(maxV*p)} stroke="#252220" strokeWidth="1"/>
      ))}
      <polygon points={area} fill="url(#lgr)"/>
      <polyline points={pts} fill="none" stroke="#F6B56D" strokeWidth="2.2" strokeLinejoin="round" strokeLinecap="round"/>
      {data.map((d,i)=>(
        <g key={i}>
          <circle cx={px(i)} cy={py(d.v)} r="3.5" fill="#F6B56D" stroke="#141210" strokeWidth="2"/>
          <text x={px(i)} y={H-1} textAnchor="middle" fontSize="9" fill="#524a3e" fontFamily="JetBrains Mono,monospace">{d.d}</text>
        </g>
      ))}
    </svg>
  );
};

const DonutChart = ({ data }) => {
  const r=38, sw=14, cx=50, cy=50, circ=2*Math.PI*r;
  const total=data.reduce((s,d)=>s+d.value,0); let cum=0;
  return (
    <svg viewBox="0 0 100 100" style={{width:120,height:120}}>
      <circle cx={cx} cy={cy} r={r} fill="none" stroke="#23201a" strokeWidth={sw}/>
      {data.map((d,i)=>{
        const pct=d.value/total, dash=circ*pct-1.5, offset=circ*(0.25-cum); cum+=pct;
        return <circle key={i} cx={cx} cy={cy} r={r} fill="none" stroke={d.color} strokeWidth={sw} strokeDasharray={`${dash} ${circ}`} strokeDashoffset={offset} strokeLinecap="round"/>;
      })}
      <text x={cx} y={cy-4} textAnchor="middle" fontSize="11" fontWeight="700" fill="#f2ebe0" fontFamily="JetBrains Mono">{data[0].value}%</text>
      <text x={cx} y={cy+8} textAnchor="middle" fontSize="6" fill="#7e7264" fontFamily="Raleway,sans-serif" letterSpacing="0.1em">TOP PKG</text>
    </svg>
  );
};

const ReportsScreen = () => {
  const [range, setRange] = useState('week');
  const chartData = range==='week' ? WEEKLY : MONTHLY;
  const totalRev  = chartData.reduce((s,d)=>s+d.v,0);
  const avgRev    = Math.round(totalRev/chartData.length);

  return (
    <div className="content">
      <div style={{display:'grid',gridTemplateColumns:'repeat(3,1fr)',gap:10,marginBottom:14}}>
        {[
          {label:'Revenue (This Month)', value:'₱258,400', sub:'+14.2% vs last month', dir:'up'},
          {label:'Sessions (This Month)', value:'486',     sub:'+52 from last month',  dir:'up'},
          {label:'Avg Session Revenue',   value:`₱${avgRev.toLocaleString()}`, sub:'Last 7 days', dir:''},
        ].map(k=>(
          <div key={k.label} className="card kpi-card">
            <div className="kpi-label">{k.label}</div>
            <div className="kpi-val" style={{fontSize:26}}>{k.value}</div>
            <div className={`kpi-delta${k.dir?' '+k.dir:''}`}>
              {k.dir==='up'&&<Icon n="arrowUp" size={11}/>}{k.sub}
            </div>
          </div>
        ))}
      </div>
      <div className="card" style={{marginBottom:12}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'14px 18px 6px'}}>
          <div>
            <div className="kpi-label">Revenue Trend</div>
            <div style={{fontFamily:'var(--font-d)',fontSize:22,fontWeight:800,letterSpacing:'-0.02em'}}>₱{totalRev.toLocaleString()} <span style={{fontSize:13,color:'var(--fg2)',fontFamily:'var(--font-s)',fontWeight:400}}>{range==='week'?'this week':'this month'}</span></div>
          </div>
          <div style={{display:'flex',background:'var(--bg1)',border:'1px solid var(--bdr2)',borderRadius:'var(--r-m)',padding:2}}>
            {[['week','This Week'],['month','This Month']].map(([v,l])=>(
              <div key={v} onClick={()=>setRange(v)} style={{padding:'4px 12px',borderRadius:4,fontSize:11,fontWeight:700,fontFamily:'var(--font-d)',cursor:'pointer',background:range===v?'var(--bg3)':'transparent',color:range===v?'var(--fg0)':'var(--fg2)'}}>
                {l}
              </div>
            ))}
          </div>
        </div>
        <div style={{padding:'0 12px 12px'}}><LineChart data={chartData}/></div>
      </div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 300px',gap:12}}>
        <div className="card">
          <div className="card-head"><span className="card-head-label">Top Meats This Month</span><span style={{fontFamily:'var(--font-m)',fontSize:11,color:'var(--fg2)'}}>by order count</span></div>
          <div style={{padding:'14px 18px'}}>
            {TOP_ITEMS.map(item=>(
              <div key={item.name} style={{marginBottom:11}}>
                <div style={{display:'flex',justifyContent:'space-between',marginBottom:4,fontSize:12.5}}>
                  <span style={{color:'var(--fg1)',fontWeight:500}}>{item.name}</span>
                  <span style={{fontFamily:'var(--font-m)',fontSize:12,color:'var(--fg2)'}}>{item.count}×</span>
                </div>
                <div style={{height:5,background:'var(--bg4)',borderRadius:99}}>
                  <div style={{height:'100%',width:`${item.pct}%`,background:'var(--accent)',borderRadius:99,opacity:.8}}/>
                </div>
              </div>
            ))}
          </div>
        </div>
        <div className="card">
          <div className="card-head"><span className="card-head-label">Package Mix</span></div>
          <div style={{padding:'14px 16px',display:'flex',flexDirection:'column',alignItems:'center'}}>
            <DonutChart data={PKG_MIX}/>
            <div style={{width:'100%',marginTop:14,display:'flex',flexDirection:'column',gap:8}}>
              {PKG_MIX.map(m=>(
                <div key={m.label} style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                  <div style={{display:'flex',alignItems:'center',gap:8}}>
                    <div style={{width:8,height:8,borderRadius:2,background:m.color,flexShrink:0}}/>
                    <span style={{fontSize:12,color:'var(--fg1)'}}>{m.label}</span>
                  </div>
                  <span style={{fontFamily:'var(--font-m)',fontSize:13,color:'var(--fg0)',fontWeight:500}}>{m.value}%</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

// ═══════════════ NAV CONFIG ══════════════════════════════

const NAV_SECTIONS = [
  { items:[{ id:'dashboard', label:'Dashboard', icon:'dashboard' }] },
  { label:'Operations', items:[
    { id:'orders',     label:'Orders',            icon:'orders',     badge:7  },
    { id:'pos',        label:'POS',               icon:'pos'                  },
    { id:'monitoring', label:'Monitoring',         icon:'monitoring'           },
    { id:'reverb',     label:'Reverb',             icon:'reverb'               },
  ]},
  { label:'Catalog', items:[
    { id:'menus',        label:'Menus',             icon:'menu'                },
    { id:'packages',     label:'Packages',          icon:'package'             },
    { id:'tablet-cats',  label:'Tablet Categories', icon:'category'            },
  ]},
  { label:'Devices', items:[
    { id:'devices',    label:'Devices',             icon:'tablet',     badge:1 },
  ]},
  { label:'People', items:[
    { id:'users',       label:'Users',              icon:'staff'               },
    { id:'roles',       label:'Roles',              icon:'role'                },
    { id:'permissions', label:'Permissions',        icon:'lock'                },
    { id:'branches',    label:'Branches',           icon:'branch',     dim:true},
  ]},
  { label:'Reports', items:[
    { id:'reports',    label:'Reports',             icon:'reports'             },
  ]},
  { label:'System', items:[
    { id:'configuration', label:'Configuration',    icon:'config'              },
    { id:'settings',      label:'Settings',         icon:'settings'            },
  ]},
];

const META = {
  dashboard:  ['Dashboard',      'Operations Overview'    ],
  orders:     ['Orders',         'Kitchen Dispatch'       ],
  pos:        ['POS',            'Live Table View'        ],
  monitoring:    ['Monitoring',     'System Health'          ],
  reverb:        ['Reverb',         'WebSocket Service'      ],
  menus:         ['Menus',          'Items & Availability'   ],
  packages:      ['Packages',       'Dining Tiers'           ],
  'tablet-cats': ['Tablet Categories','Menu Sync'            ],
  devices:       ['Devices',        'Tablet Management'      ],
  users:         ['Users',          'Staff & Permissions'    ],
  roles:         ['Roles',          'Access Control'         ],
  permissions:   ['Permissions',    'Guards & Abilities'     ],
  reports:       ['Reports',        'Analytics'              ],
  configuration: ['Configuration',  'System Hub'             ],
  settings:      ['Settings',       'App Preferences'        ],
};

// ═══════════════ SIDEBAR + TOPBAR ════════════════════════

const Sidebar = ({ active, onNav }) => (
  <nav className="sidebar">
    <div className="logo">
      <div className="logo-icon"><img src="images/woosoo-icon.png" alt="Woosoo"/></div>
      <div className="logo-text">
        <span className="logo-mark">Woosoo</span>
        <span className="logo-sub">Nexus</span>
      </div>
    </div>
    {NAV_SECTIONS.map((sec,si)=>(
      <div key={si} className="nav-section">
        {sec.label && <div className="nav-label">{sec.label}</div>}
        {sec.items.map(n=>(
          <div key={n.id} className={['nav-item',active===n.id?'active':'',n.dim?'dim':''].filter(Boolean).join(' ')} onClick={()=>!n.dim&&onNav(n.id)}>
            <Icon n={n.icon} size={14}/>
            {n.label}
            {n.badge && <span className="nav-badge">{n.badge}</span>}
          </div>
        ))}
      </div>
    ))}
    <div className="sb-footer">
      <div className="avatar">M</div>
      <div style={{display:'flex',flexDirection:'column',gap:2}}>
        <span style={{fontSize:12.5,fontWeight:700,color:'var(--fg0)',fontFamily:'var(--font-d)'}}>Manager</span>
        <span style={{fontSize:11,color:'var(--accent)',fontFamily:'var(--font-d)',fontWeight:600,letterSpacing:'0.04em'}}>Woosoo HQ</span>
      </div>
    </div>
  </nav>
);

const Topbar = ({ page, theme, onThemeChange }) => {
  const [title, crumb] = META[page] || ['',''];
  return (
    <header className="topbar">
      <div style={{display:'flex',alignItems:'center',gap:10}}>
        <span style={{fontFamily:'var(--font-d)',fontSize:18,fontWeight:800,letterSpacing:'-0.01em'}}>{title}</span>
        {crumb && <><span style={{color:'var(--bdr3)',fontSize:18,lineHeight:1}}>·</span><span style={{fontSize:10,letterSpacing:'0.12em',textTransform:'uppercase',color:'var(--fg2)',fontWeight:700,fontFamily:'var(--font-d)'}}>{crumb}</span></>}
      </div>
      <div style={{display:'flex',alignItems:'center',gap:8}}>
        <div style={{padding:'3px 10px',borderRadius:'var(--r-m)',background:'var(--accm)',border:'1px solid var(--accb)',fontSize:10.5,color:'var(--accent)',fontFamily:'var(--font-d)',fontWeight:700,letterSpacing:'0.06em'}}>HQ Branch</div>
        <div className="search-box">
          <Icon n="search" size={13}/>
          <input placeholder="Search…"/>
          <span style={{fontFamily:'var(--font-m)',fontSize:10,color:'var(--fg3)',background:'var(--bg3)',border:'1px solid var(--bdr2)',borderRadius:4,padding:'1px 5px'}}>⌘K</span>
        </div>
        <Btn ghost iconOnly title={theme==='dark'?'Switch to light mode':'Switch to dark mode'}
          onClick={()=>onThemeChange(theme==='dark'?'light':'dark')}>
          <Icon n={theme==='dark'?'sun':'moon'} size={14}/>
        </Btn>
        <Btn ghost iconOnly><Icon n="refresh" size={14}/></Btn>
        <Btn ghost iconOnly style={{position:'relative'}}>
          <Icon n="bell" size={14}/>
          <span style={{position:'absolute',top:6,right:6,width:6,height:6,background:'var(--accent)',borderRadius:'50%',border:'2px solid var(--bg0)'}}/>
        </Btn>
        <div className="avatar" style={{cursor:'default'}}>M</div>
      </div>
    </header>
  );
};

// ═══════════════ APP ROOT ════════════════════════════════

const App = () => {
  const [page, setPage]           = useState('dashboard');
  const [theme, setTheme]         = useState(()=>localStorage.getItem('nexus-theme')||'dark');
  const [collapsed, setCollapsed] = useState(false);

  React.useEffect(()=>{
    const resolved = theme==='system'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light')
      : theme;
    document.documentElement.setAttribute('data-theme', resolved);
    localStorage.setItem('nexus-theme', theme);
  }, [theme]);

  const ToastCont = getScreen('ToastContainer');

  return (
    <div className="app">
      <Sidebar active={page} onNav={setPage} collapsed={collapsed} onCollapse={()=>setCollapsed(c=>!c)}/>
      <div className="main">
        <Topbar page={page} theme={theme} onThemeChange={setTheme}/>
        {page==='dashboard'     && <Dashboard/>}
        {page==='orders'        && <OrdersScreen/>}
        {page==='pos'           && <POSScreen/>}
        {page==='monitoring'    && <MonitoringScreen/>}
        {page==='reverb'        && React.createElement(getScreen('ReverbScreen'))}
        {page==='menus'         && <MenuScreen/>}
        {page==='packages'      && <PackagesScreen/>}
        {page==='tablet-cats'   && React.createElement(getScreen('TabletCategoriesScreen'))}
        {page==='devices'       && <DevicesScreen/>}
        {page==='users'         && <UsersScreen/>}
        {page==='roles'         && React.createElement(getScreen('RolesScreen'))}
        {page==='permissions'   && React.createElement(getScreen('PermissionsScreen'))}
        {page==='reports'       && <ReportsScreen/>}
        {page==='configuration' && React.createElement(getScreen('ConfigurationScreen'),{onNav:setPage})}
        {page==='settings'      && React.createElement(getScreen('SettingsScreen'),{theme,onThemeChange:setTheme})}
      </div>
      {ToastCont && React.createElement(ToastCont)}
    </div>
  );
};

ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
