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

export type PharmacyPhone = {
    /** One format for every number: «+993 65 63 56 39». */
    display: string;
    /** For tel: links: «+99365635639». */
    dial: string;
};

export type SearchOffer = {
    id: number;
    place: number;
    /** How much dearer than the first place, e.g. "0.25"; null outside the search groups. */
    price_difference: string | null;
    pharmacy?: {
        name: string;
        phones: PharmacyPhone[];
        address: string | null;
    };
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

export type DosageForm =
    | 'tablets'
    | 'capsules'
    | 'syrup'
    | 'suspension'
    | 'injection'
    | 'drops'
    | 'spray'
    | 'suppositories'
    | 'ointment'
    | 'powder'
    | 'solution';

/** One variant of the drug: prices are only compared within one form, dose and pack size. */
export type SearchGroup = {
    key: string;
    drug_name: string;
    dosage_form: DosageForm | null;
    dosage_form_label: string | null;
    dose: string | null;
    dose_label: string | null;
    pack_count: number | null;
    pharmacies_count: number;
    offers: SearchOffer[];
};

export type SearchResult = {
    groups: SearchGroup[];
    summary: {
        variants_count: number;
        pharmacies_count: number;
        lowest_price: string | null;
    } | null;
    /** The drug shown when the query was most likely misspelt. */
    corrected_to: string | null;
};

/** The variants a visitor chose to see; null shows every value. */
export type GroupFilter = {
    form: DosageForm | null;
    dose: string | null;
    pack: number | null;
};

export type DrugSuggestion = {
    name: string;
    pharmacies_count: number;
};

export type PriceHistoryRow = {
    pack_count: number | null;
    /** The cheapest offer of each upload; an upload is missing when nobody sold it. */
    prices: { upload_id: number; offer: SearchOffer }[];
};

export type DrugSynonym = {
    id: number;
    term: string;
    synonym: string;
    created_at: string | null;
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
