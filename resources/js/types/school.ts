export type Option = { value: string | number; label: string };
export type Field = {
    key: string;
    label: string;
    type?:
        | 'text'
        | 'email'
        | 'password'
        | 'checkbox'
        | 'select'
        | 'number'
        | 'hidden';
    required?: boolean;
    options?: Option[];
    hint?: string;
    default?: string | number | boolean | null;
    fullWidth?: boolean;
    visibleWhen?: { key: string; value: string | number | boolean | null };
};
export type RecordData = { id: number; [key: string]: unknown };
export type PageLink = { url: string | null; label: string; active: boolean };
export type Results = {
    teacher_id: number;
    teacher: string;
    period_id: number;
    period: string;
    school_year: string;
    status: string;
    count: number;
    score: number;
    scale_max: number;
    performance_level: string | null;
    criteria: { prompt: string; average: number }[];
    comments: string[];
};
