const { useState, useMemo } = React;

// ═══════════════ DATA ═══════════════════════════════════

const PKG_PRICES = { 'Classic Feast': 449, 'Noble Selection': 499, 'Royal Banquet': 549 };

const MENU_CATS = [
  { id:'all', label:'All Items' }, { id:'meats', label:'Meats' },
  { id:'sides', label:'Side Dishes' }, { id:'extras', label:'Extras' },
];

const MENU_ITEMS_INIT = [
  { id:1,  cat:'meats',  img:'plain-samgyupsal.png',            name:'Plain Samgyupsal',            desc:'Classic pork belly, thinly sliced',             price:0,   avail:true  },
  { id:2,  cat:'meats',  img:'yangyeom-samgyupsal.png',         name:'Yangyeom Samgyupsal',         desc:'Marinated pork belly, house seasoning blend',   price:0,   avail:true  },
  { id:3,  cat:'meats',  img:'woosamgyup.png',                  name:'Woosamgyup',                  desc:'Premium beef samgyupsal, thinly sliced',        price:0,   avail:true  },
  { id:4,  cat:'meats',  img:'moksal-pork-neck.png',            name:'Moksal',                      desc:'Pork neck, smoky and tender',                   price:0,   avail:true  },
  { id:5,  cat:'meats',  img:'beef-bulgogi.png',                name:'Beef Bulgogi',                desc:'Marinated beef, sweet soy glaze',               price:0,   avail:true  },
  { id:6,  cat:'meats',  img:'hyangcho-woosamgyup.png',         name:'Hyangcho Woosamgyup',         desc:'Herb-marinated beef samgyupsal',                price:0,   avail:true  },
  { id:7,  cat:'meats',  img:'korean-chili-pepper-samgyupsal.png', name:'Korean Chili Samgyupsal', desc:'Spicy chili-marinated pork belly',              price:0,   avail:false },
  { id:8,  cat:'sides',  img:'kimchi.png',                      name:'Traditional Kimchi',          desc:'House-fermented napa cabbage',                  price:0,   avail:true  },
  { id:9,  cat:'sides',  img:'pickled-radish.png',              name:'Korean Pickled Radish',       desc:'Crisp radish, lightly sweet',                   price:0,   avail:true  },
  { id:10, cat:'sides',  img:'gamja-jorim.png',                 name:'Gamja Jorim',                 desc:'Braised baby potatoes, soy glaze',              price:0,   avail:true  },
  { id:11, cat:'sides',  img:'gyeran-jjim.png',                 name:'Gyeran Jjim',                 desc:'Steamed egg soufflé',                           price:0,   avail:true  },
  { id:12, cat:'sides',  img:'side-dish.png',                   name:'Banchan Set',                 desc:'Seasonal assorted side dishes',                 price:0,   avail:true  },
  { id:13, cat:'extras', img:'woosoo-cheese.png',               name:'Woosoo Cheese',               desc:'Melted cheese plate for grilling',              price:49,  avail:true  },
  { id:14, cat:'extras', img:'golden-mushroom-roll.png',        name:'Golden Mushroom Beef Roll',   desc:'Enoki mushrooms wrapped in beef slices',        price:79,  avail:true  },
  { id:15, cat:'extras', img:'dubu-ganjeong.png',               name:'Dubu Ganjeong',               desc:'Sweet & crunchy braised tofu',                  price:69,  avail:true  },
];

const ORDERS_DATA = [
  { id:'ORD-1421', table:'T-04', device:'Tablet 04', pkg:'Noble Selection',  guests:4, status:'confirmed', elapsed:52,  items:['Yangyeom Samgyupsal ×4','Woosamgyup ×2','Gamja Jorim ×2']   },
  { id:'ORD-1420', table:'T-06', device:'Tablet 06', pkg:'Classic Feast',    guests:2, status:'confirmed', elapsed:56,  items:['Plain Samgyupsal ×6','Banchan refill ×1']                    },
  { id:'ORD-1419', table:'T-01', device:'Tablet 01', pkg:'Royal Banquet',    guests:6, status:'confirmed', elapsed:88,  items:['Beef Bulgogi ×3','Moksal ×2','Woosamgyup ×3']               },
  { id:'ORD-1418', table:'T-02', device:'Tablet 02', pkg:'Noble Selection',  guests:3, status:'confirmed', elapsed:70,  items:['Hyangcho Woosamgyup ×2','Gamja Jorim ×2']                   },
  { id:'ORD-1417', table:'T-05', device:'Tablet 05', pkg:'Classic Feast',    guests:4, status:'confirmed', elapsed:95,  items:['Yangyeom Samgyupsal ×5','Gyeran Jjim ×2']                   },
  { id:'ORD-1416', table:'T-03', device:'Tablet 03', pkg:'Royal Banquet',    guests:2, status:'completed', elapsed:110, items:[]                                                             },
  { id:'ORD-1415', table:'T-07', device:'Tablet 07', pkg:'Noble Selection',  guests:5, status:'completed', elapsed:125, items:[]                                                             },
  { id:'ORD-1414', table:'T-09', device:'Tablet 09', pkg:'Classic Feast',    guests:3, status:'completed', elapsed:140, items:[]                                                             },
  { id:'ORD-1413', table:'T-10', device:'Tablet 10', pkg:'Noble Selection',  guests:2, status:'voided',    elapsed:30,  items:[]                                                             },
  { id:'ORD-1412', table:'T-11', device:'Tablet 11', pkg:'Classic Feast',    guests:4, status:'cancelled', elapsed:15,  items:[]                                                             },
];

const ORDER_DETAILS = {
  'ORD-1421': { items:[{n:'Yangyeom Samgyupsal',q:4},{n:'Woosamgyup',q:2},{n:'Gamja Jorim',q:2}],  note:'Extra scissors please',   time:'7:08 PM' },
  'ORD-1420': { items:[{n:'Plain Samgyupsal',q:6},{n:'Banchan',q:1}],                               note:'',                        time:'7:04 PM' },
  'ORD-1419': { items:[{n:'Beef Bulgogi',q:3},{n:'Moksal',q:2},{n:'Woosamgyup',q:3}],              note:'No spice on bulgogi',     time:'6:32 PM' },
  'ORD-1418': { items:[{n:'Hyangcho Woosamgyup',q:2},{n:'Gamja Jorim',q:2}],                       note:'',                        time:'6:50 PM' },
  'ORD-1417': { items:[{n:'Yangyeom Samgyupsal',q:5},{n:'Gyeran Jjim',q:2}],                      note:'Extra napkins please',    time:'6:25 PM' },
  'ORD-1416': { items:[{n:'Woosamgyup',q:3},{n:'Woosoo Cheese',q:1}],                              note:'',                        time:'6:10 PM' },
  'ORD-1415': { items:[{n:'Yangyeom Samgyupsal',q:4},{n:'Beef Bulgogi',q:2}],                      note:'',                        time:'5:55 PM' },
  'ORD-1414': { items:[{n:'Plain Samgyupsal',q:4}],                                                 note:'',                        time:'5:40 PM' },
  'ORD-1413': { items:[],                                                                            note:'No-show after 30 min',    time:'7:30 PM' },
  'ORD-1412': { items:[],                                                                            note:'Customer changed mind',   time:'7:45 PM' },
};

const ORDER_EVENTS = [
  { id:'PRT-0425', orderId:'ORD-1421', table:'T-04', isRefill:false, items:[{n:'Yangyeom Samgyupsal',q:2},{n:'Woosamgyup',q:1}],         printStatus:'printed',  time:'7:08 PM' },
  { id:'PRT-0426', orderId:'ORD-1421', table:'T-04', isRefill:true,  items:[{n:'Plain Samgyupsal',q:3}],                                 printStatus:'printed',  time:'7:24 PM' },
  { id:'PRT-0427', orderId:'ORD-1421', table:'T-04', isRefill:true,  items:[{n:'Beef Bulgogi',q:2},{n:'Woosoo Cheese',q:1}],             printStatus:'printing', time:'7:41 PM' },
  { id:'PRT-0418', orderId:'ORD-1420', table:'T-06', isRefill:false, items:[{n:'Plain Samgyupsal',q:4}],                                 printStatus:'printed',  time:'7:04 PM' },
  { id:'PRT-0419', orderId:'ORD-1420', table:'T-06', isRefill:true,  items:[{n:'Yangyeom Samgyupsal',q:2}],                             printStatus:'printed',  time:'7:28 PM' },
  { id:'PRT-0410', orderId:'ORD-1419', table:'T-01', isRefill:false, items:[{n:'Woosamgyup',q:3},{n:'Moksal',q:2}],                     printStatus:'printed',  time:'6:32 PM' },
  { id:'PRT-0411', orderId:'ORD-1419', table:'T-01', isRefill:true,  items:[{n:'Beef Bulgogi',q:3}],                                    printStatus:'printed',  time:'6:58 PM' },
  { id:'PRT-0412', orderId:'ORD-1419', table:'T-01', isRefill:true,  items:[{n:'Hyangcho Woosamgyup',q:2},{n:'Gyeran Jjim',q:1}],       printStatus:'printed',  time:'7:15 PM' },
  { id:'PRT-0413', orderId:'ORD-1419', table:'T-01', isRefill:true,  items:[{n:'Yangyeom Samgyupsal',q:2}],                             printStatus:'failed',   time:'7:38 PM' },
  { id:'PRT-0405', orderId:'ORD-1418', table:'T-02', isRefill:false, items:[{n:'Hyangcho Woosamgyup',q:2}],                             printStatus:'printed',  time:'6:50 PM' },
  { id:'PRT-0406', orderId:'ORD-1418', table:'T-02', isRefill:true,  items:[{n:'Moksal',q:3},{n:'Woosoo Cheese',q:1}],                  printStatus:'printed',  time:'7:10 PM' },
  { id:'PRT-0407', orderId:'ORD-1418', table:'T-02', isRefill:true,  items:[{n:'Beef Bulgogi',q:2}],                                    printStatus:'printing', time:'7:42 PM' },
  { id:'PRT-0399', orderId:'ORD-1417', table:'T-05', isRefill:false, items:[{n:'Plain Samgyupsal',q:5},{n:'Yangyeom Samgyupsal',q:3}],  printStatus:'printed',  time:'6:25 PM' },
  { id:'PRT-0400', orderId:'ORD-1417', table:'T-05', isRefill:true,  items:[{n:'Moksal',q:2}],                                          printStatus:'printed',  time:'6:50 PM' },
  { id:'PRT-0401', orderId:'ORD-1417', table:'T-05', isRefill:true,  items:[{n:'Gyeran Jjim',q:2}],                                    printStatus:'printed',  time:'7:05 PM' },
  { id:'PRT-0390', orderId:'ORD-1416', table:'T-03', isRefill:false, items:[{n:'Woosamgyup',q:3}],                                     printStatus:'printed',  time:'6:10 PM' },
  { id:'PRT-0391', orderId:'ORD-1416', table:'T-03', isRefill:true,  items:[{n:'Beef Bulgogi',q:2},{n:'Moksal',q:2}],                   printStatus:'printed',  time:'6:35 PM' },
  { id:'PRT-0380', orderId:'ORD-1415', table:'T-07', isRefill:false, items:[{n:'Yangyeom Samgyupsal',q:4}],                            printStatus:'printed',  time:'5:55 PM' },
  { id:'PRT-0381', orderId:'ORD-1415', table:'T-07', isRefill:true,  items:[{n:'Beef Bulgogi',q:2}],                                   printStatus:'printed',  time:'6:15 PM' },
  { id:'PRT-0382', orderId:'ORD-1415', table:'T-07', isRefill:true,  items:[{n:'Moksal',q:3},{n:'Gyeran Jjim',q:2}],                   printStatus:'printed',  time:'6:40 PM' },
  { id:'PRT-0370', orderId:'ORD-1414', table:'T-09', isRefill:false, items:[{n:'Plain Samgyupsal',q:4}],                               printStatus:'printed',  time:'5:40 PM' },
  { id:'PRT-0371', orderId:'ORD-1414', table:'T-09', isRefill:true,  items:[{n:'Yangyeom Samgyupsal',q:3}],                            printStatus:'printed',  time:'6:05 PM' },
  { id:'PRT-0360', orderId:'ORD-1413', table:'T-10', isRefill:false, items:[{n:'Yangyeom Samgyupsal',q:2}],                            printStatus:'printed',  time:'7:30 PM' },
];

const SESSIONS = [
  { id:'SES-0342', table:'T-04', guests:4, pkg:'Noble Selection', total:1996, time:'7:28 PM', status:'confirmed'  },
  { id:'SES-0341', table:'T-06', guests:2, pkg:'Classic Feast',   total:898,  time:'7:24 PM', status:'confirmed'  },
  { id:'SES-0340', table:'T-01', guests:6, pkg:'Royal Banquet',   total:3294, time:'7:15 PM', status:'confirmed'  },
  { id:'SES-0339', table:'T-02', guests:3, pkg:'Noble Selection', total:1497, time:'6:58 PM', status:'confirmed'  },
  { id:'SES-0338', table:'T-05', guests:4, pkg:'Classic Feast',   total:1796, time:'6:45 PM', status:'completed'  },
  { id:'SES-0337', table:'T-03', guests:2, pkg:'Royal Banquet',   total:1098, time:'6:32 PM', status:'completed'  },
];

const TABLETS_DATA = [
  { id:'t1', name:'Tablet 01', table:'T-01', zone:'Main Hall – Sec A', status:'online',  battery:82, ping:'12s ago', ip:'192.168.1.101', ver:'3.1.2', orders:24, sec:'active' },
  { id:'t2', name:'Tablet 02', table:'T-02', zone:'Main Hall – Sec A', status:'online',  battery:64, ping:'8s ago',  ip:'192.168.1.102', ver:'3.1.2', orders:31, sec:'active' },
  { id:'t3', name:'Tablet 03', table:'T-03', zone:'Main Hall – Sec B', status:'online',  battery:35, ping:'15s ago', ip:'192.168.1.103', ver:'3.1.2', orders:18, sec:'active' },
  { id:'t4', name:'Tablet 04', table:'T-04', zone:'Main Hall – Sec B', status:'online',  battery:91, ping:'5s ago',  ip:'192.168.1.104', ver:'3.1.2', orders:27, sec:'active' },
  { id:'t5', name:'Tablet 05', table:'T-05', zone:'Private Room 1',    status:'online',  battery:12, ping:'20s ago', ip:'192.168.1.105', ver:'3.1.2', orders:42, sec:'active' },
  { id:'t6', name:'Tablet 06', table:'T-06', zone:'Private Room 2',    status:'online',  battery:77, ping:'6s ago',  ip:'192.168.1.106', ver:'3.1.2', orders:56, sec:'active' },
  { id:'t7', name:'Tablet 07', table:'T-07', zone:'Outdoor Terrace',   status:'warning', battery:8,  ping:'3m ago',  ip:'192.168.1.107', ver:'3.0.9', orders:11, sec:'expired'},
  { id:'t8', name:'Tablet 08', table:'T-08', zone:'Outdoor Terrace',   status:'offline', battery:0,  ping:'41m ago', ip:'192.168.1.108', ver:'3.0.7', orders:9,  sec:'expired'},
];

const HOURLY = [
  {h:'10',v:0},{h:'11',v:3200},{h:'12',v:8400},{h:'13',v:12600},{h:'14',v:14800},
  {h:'15',v:9200},{h:'16',v:7400},{h:'17',v:18600},{h:'18',v:28400},{h:'19',v:34200},{h:'20',v:0},{h:'21',v:0},
];

// ═══════════════ ICONS ══════════════════════════════════

const PATHS = {
  dashboard: <><rect x="3" y="3" width="8" height="10" rx="1.2"/><rect x="13" y="3" width="8" height="6" rx="1.2"/><rect x="13" y="13" width="8" height="8" rx="1.2"/><rect x="3" y="16" width="8" height="5" rx="1.2"/></>,
  orders:    <><path d="M9 5H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></>,
  pos:       <><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M8 4v16M2 9h6M2 14h6M10 9h4M10 13h4M16 9h2M16 13h2"/></>,
  monitoring:<><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></>,
  reverb:    <><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></>,
  menu:      <><circle cx="6" cy="6" r="1.8"/><circle cx="6" cy="12" r="1.8"/><circle cx="6" cy="18" r="1.8"/><path d="M11 6h8M11 12h8M11 18h8"/></>,
  package:   <><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></>,
  category:  <><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></>,
  tablet:    <><rect x="5" y="2" width="14" height="20" rx="2.5"/><path d="M10 18h4"/></>,
  staff:     <><circle cx="12" cy="8" r="3.5"/><path d="M5 20c1-3.5 4-6 7-6s6 2.5 7 6"/></>,
  role:      <><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></>,
  lock:      <><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></>,
  branch:    <><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/></>,
  reports:   <><path d="M4 19h16"/><path d="M6 16v-5M10 16V8M14 16v-3M18 16V6"/></>,
  config:    <><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></>,
  settings:  <><circle cx="12" cy="12" r="3"/><path d="M19.4 14.5a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 010 2.8 2 2 0 01-2.8 0l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 01-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 01-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H3a2 2 0 010-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 012.8-2.8l.1.1a1.7 1.7 0 001.9.3h.1a1.7 1.7 0 001-1.5V3a2 2 0 014 0v.1a1.7 1.7 0 001 1.5h.1a1.7 1.7 0 001.9-.3l.1-.1a2 2 0 012.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9v.1a1.7 1.7 0 001.5 1H21a2 2 0 010 4h-.1a1.7 1.7 0 00-1.5 1z"/></>,
  server:    <><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></>,
  print:     <><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></>,
  search:    <><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></>,
  bell:      <><path d="M6 8a6 6 0 0112 0v5l1.5 3h-15L6 13z"/><path d="M10 20a2 2 0 004 0"/></>,
  plus:      <path d="M12 5v14M5 12h14"/>,
  filter:    <><path d="M4 6h16M7 12h10M10 18h4"/></>,
  edit:      <><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></>,
  close:     <path d="M18 6L6 18M6 6l12 12"/>,
  arrowUp:   <><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></>,
  arrowDown: <><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></>,
  refresh:   <><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></>,
  warning:   <><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></>,
  eye:       <><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></>,
  toggleOn:  <><rect x="1" y="5" width="22" height="14" rx="7"/><circle cx="16" cy="12" r="5"/></>,
  toggleOff: <><rect x="1" y="5" width="22" height="14" rx="7"/><circle cx="8" cy="12" r="5"/></>,
  trash:     <><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></>,
  check:     <polyline points="20 6 9 17 4 12"/>,
  sun:      <><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="7.05" y2="7.05"/><line x1="16.95" y1="16.95" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.22" y1="19.78" x2="7.05" y2="16.95"/><line x1="16.95" y1="7.05" x2="19.78" y2="4.22"/></>,
  moon:      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>,
  download:  <><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></>,
  key:       <><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></>,
  queue:     <><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></>,
  arrowLeft:  <><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></>,
  arrowRight: <><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></>,
};

const Icon = ({ n, size=15, sw=1.6 }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none"
    stroke="currentColor" strokeWidth={sw} strokeLinecap="round" strokeLinejoin="round"
    style={{flexShrink:0,display:'block'}}>
    {PATHS[n]||null}
  </svg>
);

const Pill = ({ label, variant='', dot=false }) => (
  <span className={`pill ${variant}`}>{dot && <span className={`pill-dot${variant==='green'?' pulse':''}`}/>}{label}</span>
);

const Btn = ({ children, primary, sm, ghost, iconOnly, danger, style, onClick }) => (
  <button
    className={['btn',primary&&'primary',sm&&'sm',ghost&&'ghost',iconOnly&&'icon-only',danger&&'danger'].filter(Boolean).join(' ')}
    style={style} onClick={onClick}>{children}
  </button>
);

// ═══════════════ DASHBOARD ══════════════════════════════

const maxV = Math.max(...HOURLY.map(h=>h.v));

const Dashboard = () => (
  <div className="content">
    {/* KPI cards */}
    <div className="kpi-grid">
      {[
        { label:'Active Devices',  value:'7 / 8',   sub:'1 warning · 1 offline', dir:'warn' },
        { label:'Open Orders',     value:'12',       sub:'+3 in last 30 min',    dir:'up'   },
        { label:'Queue Depth',     value:'3',        sub:'Background jobs',      dir:''     },
        { label:'Print Failures',  value:'0',        sub:'No failures today',    dir:''     },
      ].map(k=>(
        <div key={k.label} className="card kpi-card">
          <div className="kpi-label">{k.label}</div>
          <div className="kpi-val">{k.value}</div>
          <div className={`kpi-delta${k.dir?' '+k.dir:''}`}>
            {k.dir==='up'   && <Icon n="arrowUp"   size={11}/>}
            {k.dir==='dn'   && <Icon n="arrowDown" size={11}/>}
            {k.dir==='warn' && <Icon n="warning"   size={11}/>}
            {k.sub}
          </div>
        </div>
      ))}
    </div>

    {/* Hourly chart + live queue */}
    <div className="dash-row">
      <div className="card">
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'14px 18px 0'}}>
          <div>
            <div className="kpi-label">Hourly Revenue</div>
            <div style={{fontFamily:'var(--font-d)',fontSize:24,fontWeight:800,letterSpacing:'-0.02em'}}>₱34,200 <span style={{fontSize:13,color:'var(--fg2)',fontFamily:'var(--font-s)',fontWeight:400}}>today so far</span></div>
          </div>
          <div style={{display:'flex',background:'var(--bg1)',border:'1px solid var(--bdr2)',borderRadius:'var(--r-m)',padding:2}}>
            {['Today','Week','Month'].map((t,i)=>(
              <div key={t} style={{padding:'4px 11px',borderRadius:4,fontSize:11,fontWeight:700,cursor:'pointer',fontFamily:'var(--font-d)',background:i===0?'var(--bg3)':'transparent',color:i===0?'var(--fg0)':'var(--fg2)'}}>
                {t}
              </div>
            ))}
          </div>
        </div>
        <div className="chart-area">
          {HOURLY.map(h=>{
            const pct = maxV>0 ? (h.v/maxV)*100 : 0;
            const cls = h.v===0 ? 'future' : h.h==='19' ? 'current' : 'past';
            return (
              <div key={h.h} className="chart-col">
                <div className={`chart-bar ${cls}`} style={{height:pct>0?`${pct}%`:'3px',opacity:h.v===0?0.15:1}}/>
                <div className="chart-tick">{h.h}</div>
              </div>
            );
          })}
        </div>
        <div style={{height:12}}/>
      </div>

      <div className="card" style={{display:'flex',flexDirection:'column'}}>
        <div className="card-head">
          <span className="card-head-label">Live Queue</span>
          <Pill label="7 active" variant="accent" dot={true}/>
        </div>
        <div style={{padding:'10px 14px',display:'flex',flexDirection:'column',gap:6,flex:1}}>
          {[
            {col:'Incoming', count:3, color:'var(--blue)'},
            {col:'Grilling',  count:3, color:'var(--amber)'},
            {col:'Ready',    count:2, color:'var(--green)'},
            {col:'Served',   count:12,color:'var(--fg3)'},
          ].map(q=>(
            <div key={q.col} style={{display:'flex',alignItems:'center',gap:8,padding:'8px 12px',background:'var(--bg3)',borderRadius:'var(--r-m)',border:'1px solid var(--bdr1)'}}>
              <div style={{width:7,height:7,borderRadius:'50%',background:q.color,flexShrink:0}}/>
              <span style={{fontSize:12,fontWeight:700,letterSpacing:'0.06em',color:'var(--fg1)',fontFamily:'var(--font-d)'}}>{q.col}</span>
              <span style={{marginLeft:'auto',fontFamily:'var(--font-m)',fontSize:17,color:'var(--fg0)'}}>{q.count}</span>
            </div>
          ))}
          <div style={{borderTop:'1px solid var(--bdr1)',paddingTop:10,marginTop:4}}>
            <div className="section-title" style={{marginBottom:6}}>System health</div>
            <div style={{display:'flex',flexWrap:'wrap',gap:6}}>
              {[{label:'MySQL',ok:true},{label:'Redis',ok:true},{label:'POS DB',ok:true},{label:'Queue',ok:true}].map(s=>(
                <div key={s.label} style={{display:'flex',alignItems:'center',gap:5,padding:'4px 9px',background:'var(--bg3)',borderRadius:'var(--r-m)',border:`1px solid ${s.ok?'var(--greenb)':'var(--redb)'}`}}>
                  <div style={{width:6,height:6,borderRadius:'50%',background:s.ok?'var(--green)':'var(--red)'}}/>
                  <span style={{fontSize:11,fontFamily:'var(--font-m)',color:s.ok?'var(--green)':'var(--red)'}}>{s.label}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>

    {/* Recent sessions table */}
    <div className="card">
      <div className="card-head">
        <span className="card-head-label">Recent Sessions</span>
        <Btn sm ghost>View all</Btn>
      </div>
      <table className="tbl">
        <thead><tr><th>Session</th><th>Table</th><th>Guests</th><th>Package</th><th>Time</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          {SESSIONS.map(s=>(
            <tr key={s.id}>
              <td><span className="mono" style={{fontSize:12,color:'var(--accent)'}}>{s.id}</span></td>
              <td><span style={{fontWeight:600,fontFamily:'var(--font-d)'}}>{s.table}</span></td>
              <td><span style={{color:'var(--fg1)'}}>{s.guests} pax</span></td>
              <td><span style={{color:'var(--fg1)',fontSize:12.5}}>{s.pkg}</span></td>
              <td><span className="mono" style={{fontSize:12,color:'var(--fg2)'}}>{s.time}</span></td>
              <td><span className="mono" style={{fontWeight:600}}>₱{s.total.toLocaleString()}</span></td>
              <td><Pill label={s.status} variant={s.status==='confirmed'?'accent':s.status==='completed'?'green':'gray'} dot={s.status==='confirmed'}/></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  </div>
);

// ═══════════════ PRINT STATUS ══════════════════════════════════════════════════

const PRINT_CFG = {
  printed:  { label:'Printed',   color:'var(--green)', icon:'check'   },
  printing: { label:'Printing…', color:'var(--amber)', icon:'print'   },
  failed:   { label:'Failed',    color:'var(--red)',   icon:'warning' },
};
const PrintStatus = ({ status }) => {
  const c = PRINT_CFG[status] || PRINT_CFG.printing;
  return (
    <span style={{display:'inline-flex',alignItems:'center',gap:4,fontSize:11,color:c.color,fontFamily:'var(--font-d)',fontWeight:600}}>
      <Icon n={c.icon} size={11}/>{c.label}
    </span>
  );
};

// ═══════════════ ORDER DRAWER ════════════════════════════

const TL_STEPS = ['Confirmed','Completed'];
const TL_IDX   = { confirmed:0, completed:1, voided:1, cancelled:1 };
const STATUS_VARIANT = { confirmed:'amber', completed:'green', voided:'red', cancelled:'gray' };
const STATUS_LABEL   = { confirmed:'Confirmed', completed:'Completed', voided:'Voided', cancelled:'Cancelled' };

const OrderDrawer = ({ order, onClose }) => {
  const det = ORDER_DETAILS[order.id] || {};
  const si  = TL_IDX[order.status];
  const pkgTotal = (PKG_PRICES[order.pkg] || 0) * order.guests;
  return (
    <div className="overlay" onClick={onClose}>
      <div className="drawer" onClick={e=>e.stopPropagation()}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 18px',borderBottom:'1px solid var(--bdr2)'}}>
          <div>
            <div style={{fontFamily:'var(--font-m)',fontSize:12,color:'var(--accent)',marginBottom:3}}>{order.id}</div>
            <div style={{fontFamily:'var(--font-d)',fontSize:20,fontWeight:800,letterSpacing:'-0.01em'}}>{order.table} <span style={{color:'var(--fg2)',fontFamily:'var(--font-s)',fontSize:13,fontWeight:400}}>· {order.device}</span></div>
          </div>
          <div style={{display:'flex',gap:6,alignItems:'center'}}>
            <Pill label={STATUS_LABEL[order.status]} variant={STATUS_VARIANT[order.status]}/>
            <Btn ghost iconOnly onClick={onClose}><Icon n="close" size={14}/></Btn>
          </div>
        </div>

        {/* Package + timeline */}
        <div style={{padding:'12px 18px',borderBottom:'1px solid var(--bdr1)',background:'var(--bg0)'}}>
          <div style={{display:'flex',alignItems:'center',gap:8}}>
            <Icon n="package" size={13}/>
            <span style={{fontSize:13,fontFamily:'var(--font-d)',fontWeight:700,color:'var(--fg0)'}}>{order.pkg}</span>
            <span style={{color:'var(--fg3)',margin:'0 2px'}}>·</span>
            <span style={{fontFamily:'var(--font-m)',fontSize:13,color:'var(--fg2)'}}>{order.guests} pax</span>
            <span style={{marginLeft:'auto',fontFamily:'var(--font-m)',fontSize:14,fontWeight:600,color:'var(--fg0)'}}>₱{pkgTotal.toLocaleString()}</span>
          </div>
        </div>

        {/* Order Events */}
        <div style={{flex:1,overflowY:'auto',padding:'14px 18px'}}>
          {(()=>{
            const events = ORDER_EVENTS.filter(e=>e.orderId===order.id);
            return (
              <>
                <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:10}}>
                  <span className="section-title" style={{margin:0}}>Order Events</span>
                  <span style={{fontFamily:'var(--font-m)',fontSize:11,color:'var(--fg2)'}}>{events.length} print job{events.length!==1?'s':''}</span>
                </div>
                {events.map(evt=>(
                  <div key={evt.id} style={{marginBottom:8,padding:'10px 12px',background:'var(--bg3)',borderRadius:'var(--r-m)',border:`1px solid ${evt.printStatus==='failed'?'var(--redb)':'var(--bdr1)'}`}}>
                    <div style={{display:'flex',alignItems:'center',gap:6,marginBottom:5}}>
                      <span style={{fontFamily:'var(--font-m)',fontSize:11,color:'var(--accent)'}}>{evt.id}</span>
                      <span style={{padding:'1px 7px',borderRadius:99,fontSize:10,fontWeight:700,fontFamily:'var(--font-d)',background:evt.isRefill?'var(--bluem)':'var(--accm)',color:evt.isRefill?'var(--blue)':'var(--accent)',border:`1px solid ${evt.isRefill?'var(--blueb)':'var(--accb)'}`}}>
                        {evt.isRefill?'Refill':'Initial'}
                      </span>
                      <span style={{marginLeft:'auto',fontFamily:'var(--font-m)',fontSize:10.5,color:'var(--fg3)'}}>{evt.time}</span>
                    </div>
                    <div style={{fontSize:12,color:'var(--fg1)',marginBottom:6,lineHeight:1.6}}>
                      {evt.items.map(it=>`${it.n} ×${it.q}`).join(' · ')}
                    </div>
                    <div style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                      <PrintStatus status={evt.printStatus}/>
                      {evt.printStatus==='failed' && <Btn sm ghost style={{color:'var(--amber)'}}><Icon n="print" size={11}/>Retry</Btn>}
                    </div>
                  </div>
                ))}
                {events.length===0 && <div style={{padding:'16px 0',textAlign:'center',fontSize:12,color:'var(--fg3)'}}>No print events yet</div>}
                {det.note && <div style={{marginTop:10,padding:'9px 12px',background:'var(--bg3)',borderRadius:'var(--r-m)',border:'1px solid var(--bdr1)',fontSize:12.5,color:'var(--fg1)'}}><span style={{color:'var(--fg3)',marginRight:6,fontSize:10,fontWeight:700,letterSpacing:'0.1em',textTransform:'uppercase',fontFamily:'var(--font-d)'}}>Note</span>{det.note}</div>}
                <div style={{marginTop:10,display:'flex',gap:5,fontSize:12,color:'var(--fg2)'}}>
                  <Icon n="tablet" size={12}/><span>{det.time} · Asia/Manila</span>
                </div>
              </>
            );
          })()}
        </div>

        {order.status==='confirmed' && (
          <div style={{padding:'12px 18px',borderTop:'1px solid var(--bdr1)',display:'flex',gap:8}}>
            <Btn danger sm><Icon n="close" size={12}/>Void</Btn>
            <Btn ghost sm><Icon n="trash" size={12}/>Cancel</Btn>
            <Btn primary style={{flex:1,justifyContent:'center',height:34,fontSize:12}}>
              <Icon n="check" size={13}/>Mark Complete
            </Btn>
          </div>
        )}
        {(order.status==='completed'||order.status==='voided'||order.status==='cancelled') && (
          <div style={{padding:'12px 18px',borderTop:'1px solid var(--bdr1)',display:'flex',gap:8}}>
            <Btn ghost sm><Icon n="print" size={12}/>Print Receipt</Btn>
          </div>
        )}
      </div>
    </div>
  );
};

// ═══════════════ ORDERS SCREEN ══════════════════════════

const QCOLS = [
  {id:'confirmed', label:'Confirmed', color:'var(--amber)'},
  {id:'completed', label:'Completed', color:'var(--green)'},
  {id:'voided',    label:'Voided',    color:'var(--red)'},
  {id:'cancelled', label:'Cancelled', color:'var(--fg3)'},
];

const OCard = ({ o, onClick }) => {
  const overTime = o.status==='confirmed' && o.elapsed > 90;
  const nearing  = o.status==='confirmed' && o.elapsed > 75 && !overTime;
  return (
    <div className={`ocard${overTime?' urgent':''}`} onClick={()=>onClick(o)}>
      <div style={{display:'flex',justifyContent:'space-between',marginBottom:4}}>
        <span className="o-num">{o.id}</span>
        <span className="o-elapsed" style={{color:overTime?'var(--red)':nearing?'var(--amber)':'var(--fg2)'}}>{o.elapsed}m</span>
      </div>
      <div style={{fontSize:11,color:'var(--fg2)',marginBottom:4,display:'flex',gap:5,alignItems:'center'}}>
        <Icon n="tablet" size={10}/>{o.device} · {o.table}
      </div>
      <div style={{fontSize:11,color:'var(--accent)',fontFamily:'var(--font-d)',fontWeight:600,marginBottom:4}}>{o.pkg} · {o.guests} pax</div>
      <div className="o-items">{o.items.slice(0,2).map((item,i)=><div key={i} style={{color:i===0?'var(--fg1)':'var(--fg2)'}}>{item}</div>)}</div>
      <div className="o-footer">
        <span className="mono" style={{fontSize:12,color:'var(--fg2)'}}>₱{((PKG_PRICES[o.pkg]||0)*o.guests).toLocaleString()}</span>
        <Pill label={STATUS_LABEL[o.status]} variant={STATUS_VARIANT[o.status]}/>
      </div>
    </div>
  );
};

const OrdersScreen = () => {
  const [sel, setSel]       = useState(null);
  const [tab, setTab]       = useState('sessions');
  const [evtFilter, setEvtFilter] = useState('all');

  const filteredEvents = useMemo(()=>{
    const all = ORDER_EVENTS.slice().reverse();
    if(evtFilter==='all')     return all;
    if(evtFilter==='initial') return all.filter(e=>!e.isRefill);
    if(evtFilter==='refill')  return all.filter(e=>e.isRefill);
    return all.filter(e=>e.printStatus===evtFilter);
  },[evtFilter]);

  return (
    <div className="content">
      {sel && <OrderDrawer order={sel} onClose={()=>setSel(null)}/>}
      <div className="page-head" style={{marginBottom:14}}>
        <div>
          <div style={{display:'flex',gap:6,marginBottom:8}}>
            {[['sessions','Sessions'],['events','Order Events']].map(([id,label])=>(
              <div key={id} onClick={()=>setTab(id)} style={{padding:'5px 14px',borderRadius:'var(--r-m)',border:`1px solid ${tab===id?'var(--accb)':'var(--bdr2)'}`,background:tab===id?'var(--accm)':'transparent',cursor:'pointer',fontSize:12,fontWeight:700,fontFamily:'var(--font-d)',color:tab===id?'var(--accent)':'var(--fg2)'}}>
                {label}
              </div>
            ))}
          </div>
          {tab==='sessions' && (
            <div style={{display:'flex',gap:8}}>
              <Pill label="5 confirmed" variant="amber" dot={true}/>
              <Pill label="3 completed" variant="green"/>
              <Pill label="2 voided/cancelled" variant="gray"/>
            </div>
          )}
          {tab==='events' && (
            <div style={{fontSize:12,color:'var(--fg2)'}}>
              <span style={{fontFamily:'var(--font-m)'}}>{ORDER_EVENTS.length}</span> total print jobs today
            </div>
          )}
        </div>
        <div style={{display:'flex',gap:8}}>
          <Btn><Icon n="filter" size={13}/>Filter</Btn>
          <Btn primary onClick={()=>{ if(window.woosooChime) window.woosooChime(); }}><Icon n="refresh" size={13}/>Refresh</Btn>
        </div>
      </div>

      {tab==='sessions' && (
        <div className="queue-grid">
          {QCOLS.map(col=>{
            const colOrders = ORDERS_DATA.filter(o=>o.status===col.id);
            return (
              <div key={col.id} className="q-col">
                <div className="q-col-head">
                  <div style={{display:'flex',alignItems:'center',gap:6}}>
                    <div style={{width:7,height:7,borderRadius:'50%',background:col.color}}/>
                    <span className="q-col-title">{col.label}</span>
                  </div>
                  <span className="q-count">{colOrders.length}</span>
                </div>
                {colOrders.map(o=><OCard key={o.id} o={o} onClick={setSel}/>)}
                {colOrders.length===0 && <div style={{padding:'20px 0',textAlign:'center',fontSize:12,color:'var(--fg3)'}}>Empty</div>}
              </div>
            );
          })}
        </div>
      )}

      {tab==='events' && (
        <div className="card">
          <div style={{padding:'12px 18px',borderBottom:'1px solid var(--bdr1)',display:'flex',alignItems:'center',gap:8,flexWrap:'wrap'}}>
            {[['all','All'],['initial','Initial'],['refill','Refill'],['failed','Failed']].map(([id,label])=>(
              <div key={id} onClick={()=>setEvtFilter(id)} style={{padding:'4px 12px',borderRadius:99,fontSize:11,fontWeight:700,fontFamily:'var(--font-d)',cursor:'pointer',background:evtFilter===id?'var(--accm)':'transparent',border:`1px solid ${evtFilter===id?'var(--accb)':'var(--bdr2)'}`,color:evtFilter===id?'var(--accent)':'var(--fg2)'}}>
                {label}
              </div>
            ))}
            <span style={{marginLeft:'auto',fontFamily:'var(--font-m)',fontSize:11,color:'var(--fg2)'}}>{filteredEvents.length} events</span>
          </div>
          <table className="tbl">
            <thead><tr><th>Event</th><th>Order</th><th>Table</th><th>Type</th><th>Items</th><th>Print</th><th>Time</th><th></th></tr></thead>
            <tbody>
              {filteredEvents.map(e=>(
                <tr key={e.id}>
                  <td><span className="mono" style={{fontSize:11.5,color:'var(--accent)'}}>{e.id}</span></td>
                  <td><span className="mono" style={{fontSize:11.5,color:'var(--fg2)'}}>{e.orderId}</span></td>
                  <td><span style={{fontWeight:700,fontFamily:'var(--font-d)'}}>{e.table}</span></td>
                  <td><Pill label={e.isRefill?'Refill':'Initial'} variant={e.isRefill?'blue':'accent'}/></td>
                  <td><span style={{fontSize:12,color:'var(--fg1)'}}>{e.items.map(it=>`${it.n} ×${it.q}`).join(', ').substring(0,42)}{e.items.map(it=>`${it.n} ×${it.q}`).join(', ').length>42?'…':''}</span></td>
                  <td><PrintStatus status={e.printStatus}/></td>
                  <td><span className="mono" style={{fontSize:11.5,color:'var(--fg2)'}}>{e.time}</span></td>
                  <td style={{textAlign:'right'}}>{e.printStatus==='failed'&&<Btn sm ghost style={{color:'var(--amber)'}}><Icon n="print" size={11}/>Retry</Btn>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

// ═══════════════ MENU MODAL ══════════════════════════════

const MenuModal = ({ item, onClose, onSave }) => {
  const [form, setForm] = useState({
    name: item?.name||'', desc: item?.desc||'', price: item?.price||0,
    cat: item?.cat||'meats', img: item?.img||'', avail: item?.avail??true,
  });
  const set = (k,v) => setForm(f=>({...f,[k]:v}));
  return (
    <div className="modal-wrap" onClick={onClose}>
      <div className="modal" onClick={e=>e.stopPropagation()}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'14px 18px',borderBottom:'1px solid var(--bdr2)'}}>
          <div style={{fontFamily:'var(--font-d)',fontSize:18,fontWeight:800}}>{item?'Edit Item':'New Item'}</div>
          <Btn ghost iconOnly onClick={onClose}><Icon n="close" size={14}/></Btn>
        </div>
        <div style={{overflowY:'auto',padding:'16px 18px',display:'flex',flexDirection:'column',gap:14}}>
          {/* Image preview */}
          <div style={{display:'flex',gap:14,alignItems:'center'}}>
            <div style={{width:64,height:64,borderRadius:'var(--r-l)',overflow:'hidden',background:'var(--bg3)',border:'1px solid var(--bdr2)',flexShrink:0}}>
              {form.img ? <img src={(window.__resources&&window.__resources[form.img])||`images/food/${form.img}`} style={{width:'100%',height:'100%',objectFit:'cover'}} alt=""/> : <div style={{width:'100%',height:'100%',display:'flex',alignItems:'center',justifyContent:'center',fontSize:28}}>🍖</div>}
            </div>
            <div style={{flex:1}}>
              <div className="form-label">Image Filename</div>
              <input className="form-input" value={form.img} onChange={e=>set('img',e.target.value)} placeholder="e.g. samgyupsal.png"/>
            </div>
          </div>
          <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12}}>
            <div><div className="form-label">Name</div><input className="form-input" value={form.name} onChange={e=>set('name',e.target.value)} placeholder="Item name"/></div>
            <div><div className="form-label">Add-on Price (₱, 0 = included)</div><input className="form-input" type="number" value={form.price} onChange={e=>set('price',e.target.value)} placeholder="0"/></div>
          </div>
          <div><div className="form-label">Description</div><textarea className="form-textarea" value={form.desc} onChange={e=>set('desc',e.target.value)} placeholder="Short description…"/></div>
          <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12}}>
            <div>
              <div className="form-label">Category</div>
              <select className="form-select" value={form.cat} onChange={e=>set('cat',e.target.value)} style={{width:'100%'}}>
                {MENU_CATS.filter(c=>c.id!=='all').map(c=><option key={c.id} value={c.id}>{c.label}</option>)}
              </select>
            </div>
            <div>
              <div className="form-label">Availability</div>
              <div style={{display:'flex',gap:8,marginTop:2}}>
                {[true,false].map(v=>(
                  <div key={String(v)} onClick={()=>set('avail',v)} style={{flex:1,textAlign:'center',padding:'5px 0',borderRadius:'var(--r-m)',cursor:'pointer',fontSize:12,fontWeight:600,fontFamily:'var(--font-d)',background:form.avail===v?v?'var(--greenm)':'var(--redm)':'var(--bg3)',border:`1px solid ${form.avail===v?v?'var(--greenb)':'var(--redb)':'var(--bdr2)'}`,color:form.avail===v?v?'var(--green)':'var(--red)':'var(--fg2)'}}>
                    {v?'Available':'Unavailable'}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
        <div style={{display:'flex',gap:8,padding:'12px 18px',borderTop:'1px solid var(--bdr1)'}}>
          {item && <Btn ghost style={{color:'var(--red)',marginRight:'auto'}}><Icon n="trash" size={13}/>Delete</Btn>}
          <Btn ghost onClick={onClose}>Cancel</Btn>
          <Btn primary onClick={()=>onSave(form)}><Icon n="check" size={13}/>Save Item</Btn>
        </div>
      </div>
    </div>
  );
};

// ═══════════════ MENU SCREEN ════════════════════════════

const MenuScreen = () => {
  const [cat, setCat]       = useState('all');
  const [search, setSearch] = useState('');
  const [items, setItems]   = useState(MENU_ITEMS_INIT);
  const [modal, setModal]   = useState(null);

  const catCounts = useMemo(()=>{ const m={}; items.forEach(i=>{m[i.cat]=(m[i.cat]||0)+1;}); return m; },[items]);
  const filtered  = useMemo(()=>{
    let list = cat!=='all' ? items.filter(i=>i.cat===cat) : items;
    if(search) list = list.filter(i=>i.name.toLowerCase().includes(search.toLowerCase()));
    return list;
  },[cat,search,items]);

  const toggle   = id => setItems(prev=>prev.map(i=>i.id===id?{...i,avail:!i.avail}:i));
  const handleSave = form => {
    if(modal==='new') setItems(prev=>[...prev,{...form,id:Date.now(),price:parseFloat(form.price)||0}]);
    else setItems(prev=>prev.map(i=>i.id===modal.id?{...i,...form,price:parseFloat(form.price)||0}:i));
    setModal(null);
  };

  return (
    <div className="content">
      {modal && <MenuModal item={modal==='new'?null:modal} onClose={()=>setModal(null)} onSave={handleSave}/>}
      <div className="page-head" style={{marginBottom:14}}>
        <div style={{display:'flex',gap:8}}>
          <div className="search-box" style={{minWidth:260}}>
            <Icon n="search" size={13}/>
            <input placeholder="Search menu items…" value={search} onChange={e=>setSearch(e.target.value)}/>
          </div>
        </div>
        <Btn primary onClick={()=>setModal('new')}><Icon n="plus" size={13}/>Add Item</Btn>
      </div>
      <div className="menu-layout">
        <div className="cat-list">
          <div className="nav-label" style={{paddingTop:4,paddingBottom:6}}>Categories</div>
          {MENU_CATS.map(c=>(
            <div key={c.id} className={`cat-item${cat===c.id?' active':''}`} onClick={()=>setCat(c.id)}>
              {c.label}
              <span className="cat-count">{c.id==='all'?items.length:catCounts[c.id]||0}</span>
            </div>
          ))}
        </div>
        <div>
          <div style={{fontSize:12,color:'var(--fg2)',marginBottom:10,fontFamily:'var(--font-s)'}}>
            <span style={{fontFamily:'var(--font-m)'}}>{filtered.length}</span> item{filtered.length!==1?'s':''} · <span style={{color:'var(--red)'}}>{filtered.filter(i=>!i.avail).length} unavailable</span>
          </div>
          <div className="item-grid">
            {filtered.map(item=>(
              <div key={item.id} className={`card mcard${!item.avail?' unavail':''}`}>
                <div style={{display:'flex',gap:10,marginBottom:9}}>
                  <div className="m-thumb">
                    {item.img ? <img src={(window.__resources&&window.__resources[item.img])||`images/food/${item.img}`} alt={item.name} style={{width:'100%',height:'100%',objectFit:'cover'}}/> : <span style={{fontSize:20}}>🍖</span>}
                  </div>
                  <div style={{display:'flex',flexDirection:'column',gap:2,flex:1,minWidth:0}}>
                    <div className="m-name">{item.name}</div>
                    <div className="m-desc">{item.desc}</div>
                  </div>
                </div>
                <div className="m-footer">
                  <span className="m-price">{item.price>0?`+₱${item.price}`:<span style={{fontSize:11,color:'var(--fg3)',fontFamily:'var(--font-d)',letterSpacing:'0.06em',textTransform:'uppercase'}}>Included</span>}</span>
                  <div style={{display:'flex',gap:6}}>
                    <Btn sm ghost onClick={()=>toggle(item.id)} style={{color:item.avail?'var(--green)':'var(--fg3)',gap:5}}>
                      <Icon n={item.avail?'toggleOn':'toggleOff'} size={13}/>{item.avail?'On':'Off'}
                    </Btn>
                    <Btn sm ghost onClick={()=>setModal(item)}><Icon n="edit" size={12}/></Btn>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

// ═══════════════ DEVICES SCREEN ═════════════════════════

const battCls = p => p>50?'hi':p>20?'mid':'lo';

const DeviceCard = ({ t }) => (
  <div className="card tcard">
    <div style={{display:'flex',gap:10,alignItems:'center',marginBottom:12}}>
      <div className={`dev-icon ${t.status}`}><Icon n="tablet" size={20}/></div>
      <div style={{display:'flex',flexDirection:'column',gap:2,flex:1}}>
        <span className="dev-name">{t.name}</span>
        <span className="dev-zone">{t.zone}</span>
      </div>
      <Pill label={t.status==='online'?'Online':t.status==='warning'?'Warning':'Offline'}
        variant={t.status==='online'?'green':t.status==='warning'?'amber':'red'} dot={t.status==='online'}/>
    </div>
    <div className="meta-grid">
      {[{label:'Table',val:t.table},{label:'Last Ping',val:t.ping},{label:'App Ver',val:`v${t.ver}`},{label:'IP',val:t.ip}].map(m=>(
        <div key={m.label}><div className="meta-label">{m.label}</div><div className="meta-val">{m.val}</div></div>
      ))}
    </div>
    {t.battery>0 ? (
      <>
        <div style={{display:'flex',justifyContent:'space-between',fontSize:11,color:'var(--fg2)',marginBottom:5}}>
          <span>Battery</span>
          <span className="mono" style={{color:t.battery<=20?'var(--red)':t.battery<=50?'var(--amber)':'var(--fg1)'}}>{t.battery}%</span>
        </div>
        <div className="batt-wrap"><div className={`batt-fill ${battCls(t.battery)}`} style={{width:`${t.battery}%`}}/></div>
      </>
    ) : (
      <div style={{display:'flex',gap:6,fontSize:12,color:'var(--red)',padding:'4px 0'}}>
        <Icon n="warning" size={12}/>Battery depleted
      </div>
    )}
    <div style={{display:'flex',gap:6,marginTop:10,paddingTop:10,borderTop:'1px solid var(--bdr1)'}}>
      <div style={{flex:1,display:'flex',alignItems:'center',gap:5,fontSize:11,color:t.sec==='active'?'var(--green)':'var(--amber)',fontFamily:'var(--font-m)'}}>
        <Icon n="key" size={11}/>{t.sec==='active'?'Sec OK':'Expired'}
      </div>
      <Btn sm ghost><Icon n="eye" size={12}/>View</Btn>
      <Btn sm ghost><Icon n="refresh" size={12}/>Restart</Btn>
    </div>
  </div>
);

const DevicesScreen = () => (
  <div className="content">
    <div className="page-head" style={{marginBottom:14}}>
      <div style={{display:'flex',gap:8,flexWrap:'wrap'}}>
        <Pill label="6 online" variant="green" dot={true}/>
        <Pill label="1 warning" variant="amber" dot={true}/>
        <Pill label="1 offline" variant="red" dot={true}/>
      </div>
      <div style={{display:'flex',gap:8}}>
        <Btn><Icon n="download" size={13}/>APK Download</Btn>
        <Btn><Icon n="refresh" size={13}/>Sync All</Btn>
        <Btn primary><Icon n="plus" size={13}/>Add Device</Btn>
      </div>
    </div>
    <div className="card" style={{marginBottom:14,padding:'12px 18px'}}>
      <div style={{display:'grid',gridTemplateColumns:'repeat(4,1fr)',gap:0}}>
        {[{label:'Orders Today',val:'218'},{label:'Avg Battery',val:'51%'},{label:'App Version',val:'v3.1.2'},{label:'Network',val:'LAN ✓'}].map((m,i)=>(
          <div key={m.label} style={{padding:'0 16px',borderLeft:i>0?'1px solid var(--bdr1)':'none'}}>
            <div className="meta-label">{m.label}</div>
            <div style={{fontFamily:'var(--font-m)',fontSize:18,color:'var(--fg0)',marginTop:2}}>{m.val}</div>
          </div>
        ))}
      </div>
    </div>
    <div className="tab-grid">{TABLETS_DATA.map(t=><DeviceCard key={t.id} t={t}/>)}</div>
  </div>
);

// ═══════════════ EXPORTS ════════════════════════════════

Object.assign(window, {
  MENU_CATS, MENU_ITEMS_INIT, ORDERS_DATA, ORDER_DETAILS, ORDER_EVENTS, TABLETS_DATA, SESSIONS, HOURLY, PKG_PRICES,
  Icon, Pill, Btn, PrintStatus,
  Dashboard, OrdersScreen, MenuScreen, DevicesScreen,
});
