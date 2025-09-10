import { h } from 'vue'
import { 
    // MoveDown, 
    // MoveRight, 
    // MoveUp,
    CircleCheck,
    Circle,
    // CirclePlus,
    // MessageSquareReply,
    // Timer,
} from 'lucide-vue-next'

export const labels = [
  {
    value: 'bug',
    label: 'Bug',
  },
  {
    value: 'feature',
    label: 'Feature',
  },
  {
    value: 'documentation',
    label: 'Documentation',
  },
];

export const roles = [
  {
    value: 'owner',
    label: 'Owner',
    icon: h(Circle),
  },
  {
    value: 'accountant',
    label: 'Accountant',
     icon: h(Circle),
  },
  {
    value: 'manager',
    label: 'Manager',
     icon: h(Circle),
  },
]

export const statuses = [
  {
    value: 'active',
    label: 'Active',
    icon: h(CircleCheck),
  },
  {
    value: 'inactive',
    label: 'Inactive',
    icon: h(Circle),
  },
  // {
  //   value: 'in progress',
  //   label: 'In Progress',
  //   icon: h(Timer),
  // },
  // {
  //   value: 'done',
  //   label: 'Done',
  //   icon: h(CircleCheck),
  // },
  // {
  //   value: 'canceled',
  //   label: 'Canceled',
  //   icon: h(CirclePlus),
  // },
]

export const priorities = [
  // {
  //   value: 'low',
  //   label: 'Low',
  //   icon: h(MoveDown),
  // },
  // {
  //   value: 'medium',
  //   label: 'Medium',
  //   icon: h(MoveRight),
  // },
  // {
  //   value: 'high',
  //   label: 'High',
  //   icon: h(MoveUp),
  // },
]
