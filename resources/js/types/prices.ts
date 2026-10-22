export type PriceUploadStatus =
    | 'uploaded'
    | 'processing'
    | 'needs_review'
    | 'active'
    | 'archived'
    | 'failed';

export type PriceUploadSheetStatus =
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
