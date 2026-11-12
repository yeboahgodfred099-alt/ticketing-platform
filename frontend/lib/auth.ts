import { apiClient } from "./api-client";
import { API_BASE_URL } from "./constants";

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  is_active: boolean;
  has_mfa: boolean;
  email_verified: boolean;
  last_login_at: string | null;
  roles: Array<{
    id: string;
    name: string;
    description: string;
    permissions?: Array<{ id: string; name: string }>;
  }>;
}

export interface LoginResponse {
  requires_mfa: boolean;
  mfa_setup_required?: boolean;
  message?: string;
  admin?: AdminUser;
}

export interface MfaSetupResponse {
  secret: string;
  qr_code_url: string;
  message: string;
}

export interface MfaConfirmResponse {
  message: string;
  recovery_codes: string[];
  admin: AdminUser;
}

/**
 * Fetch CSRF cookie from Laravel Sanctum prior to login.
 */
export async function getCsrfCookie(): Promise<void> {
  const sanctumUrl = API_BASE_URL.replace("/api/v1", "/sanctum/csrf-cookie");
  try {
    await fetch(sanctumUrl, {
      credentials: "include",
      headers: {
        Accept: "application/json",
      },
    });
  } catch {
    // Ignore error in local dev or SSR if cookie already present
  }
}

/**
 * Get current authenticated admin user profile.
 */
export async function getCurrentAdmin(): Promise<AdminUser | null> {
  try {
    const res = await apiClient<{ admin: AdminUser }>("/admin/me");
    return res.admin;
  } catch {
    return null;
  }
}

/**
 * Log out the current admin session.
 */
export async function logout(): Promise<void> {
  await apiClient("/auth/logout", { method: "POST" });
}
