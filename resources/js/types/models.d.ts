// Use an interface for object shapes
import { OrderStatus } from '@/types/enums';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Branch {
    id: number;
    name: string;
}

export interface Table {
    id: number;
    name: string,
    status: string;
    is_available: boolean;
    is_locked: boolean;
    device: Device;
    tableOrder: TableOrder;
}

export interface TableOrder {
    id: number;
    table_id: number;
    order_id: number;
    is_cleared: boolean;
    is_printed: boolean;
    table?: Table;
    order?: Order;
}


export interface Device {
    id: number;
    table_id: number;
    branch_id: number;
    name: string;
    is_active: boolean;
    ip_address?: string;
    port?: number;
    branch?: Branch;
    table?: Table;
}

export interface DeviceOrder {
    id: number;
    name: string;
    device_id: number;
    order_id: number;
    order_number: string;
    status: OrderStatus;
    device?: Device;
    order?: Order;
    table?: Table;
    meta: any;
}
export interface Menu {
    id: number;
    menu_group_id: number;
    menu_tax_type_id: number;
    menu_category_id: number;
    menu_course_type_id: number;
    name: string;
    kitchen_name: string;
    receipt_name: string;
    price: number;
    group: string;
    category: string;
    course: string;
    img_url: string;
    img_path: string;
    cost: number;
    description: string;
    index: number;
    is_taxable: boolean;
    is_available: boolean;
    is_modifier: boolean;
    is_discountable: boolean;
    tare_weight: number;
    scale_unit: string;
    measurement_unit: string;
    is_locked: boolean;
    quantity: number;
    in_stock: number;
    is_modifier_only: boolean;
    guest_count: number;
}

export interface Order {
    id: number;
    date_time_opened: date;
    transaction_no: number;
    guest_count: number;
    reprint_count: number;
    orderCheck?: OrderCheck;
    orderedMenus?: OrderedMenu[];
    device?: Device;
    deviceOrder?: DeviceOrder;
    table?: Table;
}
export interface OrderedMenu {
    menu_id: number;
    quantity: number;
    price_level_id: number;
    menu?: Menu;
}
export interface OrderCheck {
    order_id: number;
    date_time_opened: string;
    is_voided: boolean;
    is_settled: boolean;
    total_amount: decimal;
    paid_amount: number;
    change: number;
    subtotal_amount: number;
    transaction_number: number;
    guest_count: number;
}

export interface DeviceRegistrationCode {
    id: number;
    code: number;
    device: Device;
    used_by_device_id: number;
}



    