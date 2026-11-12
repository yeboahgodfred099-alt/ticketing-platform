"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AdminUser, getCurrentAdmin, logout } from "@/lib/auth";

export function useAuth(requireAuth: boolean = true) {
  const [admin, setAdmin] = useState<AdminUser | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const router = useRouter();

  useEffect(() => {
    let isMounted = true;

    getCurrentAdmin()
      .then((user) => {
        if (!isMounted) return;
        setAdmin(user);
        setLoading(false);

        if (requireAuth && !user) {
          router.replace("/login");
        }
      })
      .catch(() => {
        if (!isMounted) return;
        setAdmin(null);
        setLoading(false);
        if (requireAuth) {
          router.replace("/login");
        }
      });

    return () => {
      isMounted = false;
    };
  }, [requireAuth, router]);

  const handleLogout = async () => {
    try {
      await logout();
    } finally {
      setAdmin(null);
      router.push("/login");
    }
  };

  const hasRole = (roleName: string): boolean => {
    return admin?.roles.some((r) => r.name === roleName) ?? false;
  };

  const hasPermission = (permissionName: string): boolean => {
    return (
      admin?.roles.some((r) =>
        r.permissions?.some((p) => p.name === permissionName)
      ) ?? false
    );
  };

  return {
    admin,
    loading,
    logout: handleLogout,
    hasRole,
    hasPermission,
  };
}

