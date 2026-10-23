export type PriceUploadStatus =
    | 'uploaded'
    | 'processing'
    | 'needs_review'
    | 'active'
    | 'archived'
    | 'failed';

export type PriceUploadSheetStatus =
    | 'processing'
    | 'parsed'
    | 'needs_review'
    | 'skipped'
    | 'failed';

export type PriceUploadSheet = {
    id: number;
    sheet_name: string;
    sheet_index: number;
    status: PriceUploadSheetStatus;
    rows_parsed: number;
    review_reason: string | null;
};

export type PreviewCell = string | number | boolean | null;

/** Row numbers are as in Excel (1-based); column indexes are zero-based (A = 0). */
export type PriceUploadSheetDetail = PriceUploadSheet & {
    header_row: number | null;
    name_col: number | null;
    price_col: number | null;
    price_discounted_col: number | null;
    preview: { row: number; cells: PreviewCell[] }[];
};

export type PriceItem = {
    id: number;
    source_row: number;
    raw_name: string;
    base_name: string;
    pack_count: number | null;
    dose: string | null;
    price: string;
    price_discounted: string | null;
    effective_price: string;
    goods_discount_percent: string | null;
    discount_note: string | null;
    manufacturer: string | null;
};

export type DiscountRule = {
    id: number;
    type: 'price' | 'goods';
    percent: string;
    min_sum: string | null;
    is_conditional: boolean;
    raw_text: string;
};

export type PriceUpload = {
    id: number;
    original_filename: string;
    status: PriceUploadStatus;
    sheets_total: number;
    sheets_processed?: number;
    sheets_ok: number;
    rows_total: number;
    uploaded_by?: string | null;
    activated_at: string | null;
    created_at: string | null;
    sheets?: PriceUploadSheet[];
};

export type SearchOffer = {
    id: number;
    place: number;
    pharmacy?: { name: string; phone: string | null };
    raw_name: string;
    dose: string | null;
    manufacturer: string | null;
    price: string;
    price_discounted: string | null;
    effective_price: string;
    goods_discount_percent: string | null;
    discount_note: string | null;
    sheet_discounts?: DiscountRule[];
};

export type SearchGroup = {
    pack_count: number | null;
    offers: SearchOffer[];
};

export type Paginated<T> = {
    data: T[];
    links: {
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
};
