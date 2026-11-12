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
        | 'school-year'
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
export type EvaluationTemplate = {
    id: number;
    template_id: number | null;
    version: number;
    is_locked: boolean;
    name: string;
    description: string | null;
    scale_min: number;
    scale_max: number;
    is_active: boolean;
    periods_count: number;
    periods: {
        id: number;
        name: string;
        school_year: string;
        status: string;
    }[];
    criteria: { prompt: string; weight: number | string }[];
    performance_bands: { label: string; min: number; max: number }[] | null;
};
export type Results = {
    teacher_id: number;
    teacher: string;
    period_id: number;
    period: string;
    school_year: string;
    status: string;
    count: number;
    score: number;
    template_name: string;
    template_version: number;
    scale_max: number;
    performance_level: string | null;
    criteria: { prompt: string; average: number }[];
    comments: string[];
};
