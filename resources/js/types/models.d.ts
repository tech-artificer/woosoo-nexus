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

export interface Device {
    id: number;
    table_id: number;
    name: string;
    ip_address?: string;
    port?: number;
    table?: Table;

}
export interface DeviceOrder {
    id: number;
    device_id: number;
    order_id: number;
    order_number: string;
    status: OrderStatus;
    device?: Device;
    order?: Order;
    table?: Table;
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
    group: MenuGroup;
    category: MenuTaxType;
    course: MenuCourseType;
    img_url: string;
}

export interface Order {
    id: number;
    date_time_opened: date;
    transaction_no: number;
    guest_count: number;
    order_check?: OrderCheck;
    ordered_menus?: OrderedMenu[];
    device?: Device;
    deviceOrder?: DeviceOrder;
    table?: Table;
}
export interface OrderedMenu {
    menu_id: number;
    quantity: number;
    price_level_id: number;
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
export interface Table {
    id: number;
    name: string,
    status: string;
    is_available: boolean;
    is_locked: boolean;
}

    