import { API_BASE_URL } from "./constants";

export interface ApiResponse<T = unknown> {
  data: T;
  message?: string;
  meta?: Record<string, unknown>;
}

export interface ApiErrorResponse {
  message: string;
  errors?: Record<string, string[]>;
  code?: string;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
    public code?: string
  ) {
    super(message);
    this.name = "ApiError";
  }
}

/**
 * Fetch wrapper with credentials and standard JSON handling
 */
export async function apiClient<T = unknown>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  let path = endpoint;
  if (!endpoint.startsWith("http")) {
    if (path.startsWith("/api/v1")) {
      path = path.slice(7);
    } else if (path.startsWith("api/v1")) {
      path = path.slice(6);
    }
  }

  const url = endpoint.startsWith("http")
    ? endpoint
    : `${API_BASE_URL}${path.startsWith("/") ? "" : "/"}${path}`;

  const defaultHeaders: Record<string, string> = {
    Accept: "application/json",
    ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" }),
  };

  const response = await fetch(url, {
    ...options,
    credentials: "include",
    headers: {
      ...defaultHeaders,
      ...options.headers,
    },
  });

  if (!response.ok) {
    let errorData: ApiErrorResponse;
    try {
      errorData = await response.json();
    } catch {
      errorData = { message: `Request failed with status ${response.status}` };
    }

    throw new ApiError(
      errorData.message || "An unexpected error occurred",
      response.status,
      errorData.errors,
      errorData.code
    );
  }

  // Handle 204 No Content
  if (response.status === 204) {
    return {} as T;
  }

  return response.json();
}

