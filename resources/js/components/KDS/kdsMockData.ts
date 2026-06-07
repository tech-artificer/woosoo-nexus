import type { KdsTicket } from './kdsTypes'

const now = Date.now()

function issuedAt(secondsAgo: number): number {
  return now - secondsAgo * 1000
}

export const kdsMockTickets: KdsTicket[] = [
  {
    id: 'K-1182',
    table: 'T-05',
    type: 'initial',
    issued: '7:23 PM',
    issuedAt: issuedAt(26 * 60 + 14),
    elapsed: 26 * 60 + 14,
    state: 'preparing',
    items: [
      { id: '1182-1', qty: 3, name: 'Woosamgyup', done: true },
      { id: '1182-2', qty: 2, name: 'Hyangcho Woosamgyup', done: true },
      { id: '1182-3', qty: 2, name: 'Beef Bulgogi - no garlic', done: false, safety: true },
      { id: '1182-4', qty: 1, name: 'Banchan Set', done: false },
      { id: '1182-5', qty: 1, name: 'Gyeran Jjim', done: false },
      { id: '1182-6', qty: 2, name: 'Soju - Chamisul', done: false },
      { id: '1182-7', qty: 1, name: 'Sprite 1L', done: false },
    ],
  },
  {
    id: 'K-1183',
    table: 'T-11',
    type: 'initial',
    issued: '7:46 PM',
    issuedAt: issuedAt(3 * 60 + 12),
    elapsed: 3 * 60 + 12,
    state: 'new',
    items: [
      { id: '1183-1', qty: 3, name: 'Woosamgyup', done: false },
      { id: '1183-2', qty: 2, name: 'Hyangcho Woosamgyup', done: false },
      { id: '1183-3', qty: 2, name: 'Beef Bulgogi', done: false },
      { id: '1183-4', qty: 1, name: 'Banchan Set', done: false },
      { id: '1183-5', qty: 1, name: 'Gyeran Jjim', done: false },
      { id: '1183-6', qty: 2, name: 'Soju - Chamisul', done: false },
      { id: '1183-7', qty: 1, name: 'Sprite 1L', done: false },
    ],
  },
  {
    id: 'K-1173',
    table: 'T-10',
    type: 'refill',
    issued: '7:54 PM',
    issuedAt: issuedAt(2 * 60 + 5),
    elapsed: 2 * 60 + 5,
    state: 'new',
    items: [
      { id: '1173-1', qty: 2, name: 'Kimchi', done: false },
      { id: '1173-2', qty: 1, name: 'Lettuce Wrap', done: false },
      { id: '1173-3', qty: 1, name: 'Yangyeom Samgyupsal', done: false },
    ],
  },
  {
    id: 'K-1188',
    table: 'T-05',
    type: 'refill',
    issued: '7:55 PM',
    issuedAt: issuedAt(89),
    elapsed: 89,
    state: 'new',
    items: [
      { id: '1188-1', qty: 2, name: 'Kimchi', done: false },
      { id: '1188-2', qty: 1, name: 'Pickled Radish', done: false },
    ],
  },
  {
    id: 'K-1176',
    table: 'T-08',
    type: 'initial',
    issued: '7:38 PM',
    issuedAt: issuedAt(14 * 60 + 22),
    elapsed: 14 * 60 + 22,
    state: 'preparing',
    items: [
      { id: '1176-1', qty: 2, name: 'Royal Banquet Set', done: true },
      { id: '1176-2', qty: 2, name: 'Galbi', done: true },
      { id: '1176-3', qty: 1, name: 'Haemul Pajeon', done: false },
    ],
  },
  {
    id: 'K-1178',
    table: 'T-03',
    type: 'initial',
    issued: '7:43 PM',
    issuedAt: issuedAt(8 * 60 + 42),
    elapsed: 8 * 60 + 42,
    state: 'ready',
    items: [
      { id: '1178-1', qty: 2, name: 'Classic Feast', done: true },
      { id: '1178-2', qty: 1, name: 'Corn Cheese', done: true },
      { id: '1178-3', qty: 2, name: 'Iced Tea', done: true },
    ],
  },
  {
    id: 'K-1168',
    table: 'T-07',
    type: 'initial',
    issued: '7:12 PM',
    issuedAt: issuedAt(41 * 60),
    elapsed: 41 * 60,
    frozenElapsed: 24 * 60 + 16,
    state: 'served',
    recalled: 1,
    items: [
      { id: '1168-1', qty: 4, name: 'Noble Selection', done: true },
      { id: '1168-2', qty: 2, name: 'Kimchi Fried Rice', done: true },
    ],
  },
  {
    id: 'K-1169',
    table: 'T-02',
    type: 'refill',
    issued: '7:18 PM',
    issuedAt: issuedAt(36 * 60),
    elapsed: 36 * 60,
    frozenElapsed: 5 * 60 + 45,
    state: 'voided',
    voidReason: 'FOH voided duplicate refill',
    items: [
      { id: '1169-1', qty: 1, name: 'Steamed Egg', done: false },
      { id: '1169-2', qty: 1, name: 'Rice', done: false },
    ],
  },
]
