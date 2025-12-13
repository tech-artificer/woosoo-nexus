// Use an interface for object shapes
import { OrderStatus } from '@/types/enums';

export interface User {
    id: number;
    name: string;
    email: string;
    status: string;
    avatar?: string;
    role: string;
    is_admin: boolean;
    
    deleted_at?: string;
    roles: Role[];
}

export interface Role {
    id: number;
    name: string;
    permissions: Permission[];
}

export interface Permission {
    id: number;
    name: string;
    label: string;
    guard_name: string;
}

export interface Branch {
    id: number;
    name: string;
    location: string;
}

export interface Table {
    id: number;
    name: string,
    status: string;
    is_available: boolean;
    is_locked: boolean;
    device: Device;
    tableOrder?: TableOrder;
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
    name: string;
    table_id: number;
    branch_id: number;
    is_active: boolean;
    ip_address?: string;
    deleted_at?: string;
    registration_code?: DeviceRegistrationCode;
    port?: number;
    branch?: Branch;
    table?: Table;
}

export interface DeviceOrder {
    items: OrderedMenu[] | [];
    id: number;
    guest_count: number;
    name: string;
    device_id: number;
    order_id: number | string | null;
    order_number: string;
    status: OrderStatus;
    device?: Device | null;
    order?: Order | null;
    table?: Table | null;
    total: number;
    created_at: string;
    updated_at: string;
    meta: any | null;
    __is_refill?: boolean;
    is_voided: boolean;
    is_settled: boolean;
    is_printed: boolean;
    printed_at?: string | null;
    printed_by?: string | null;
    deleted_at?: string;
    status: string;
    service_requests: ServiceRequest[] | []
    tablename?: string;
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
    description: string;
    index: number;
    is_taxable: boolean;
    is_available: boolean;
    is_modifier: boolean;
    is_discountable: boolean;
    quantity: number;
    guest_count: number;
}

export interface Order {
    id: number;
    date_time_opened: string;
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
    id?: number;
    menu_id: number;
    name?: string;
    quantity: number;
    price_level_id?: number;
    price?: number;
    subtotal?: number;
    notes?: string | null;
    menu?: Menu;
}
export interface OrderCheck {
    order_id: number;
    date_time_opened: string;
    is_voided: boolean;
    is_settled: boolean;
    total_amount: number;
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

export interface ServiceRequest {
    device_order_id: number;
    order_id: number;
    table_service_id: number;
    table_service_name: string;
}

    