export type PaginatedMeta = {
  current_page: number;
  from: number | null;
  last_page: number;
  path: string;
  per_page: number;
  to: number | null;
  total: number;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: PaginatedMeta;
  links?: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
};

export type DataResponse<T> = {
  data: T;
};

export type ListParams = {
  page?: number;
  per_page?: number;
  q?: string;
  [key: string]: string | number | boolean | undefined;
};
