export type TenantRole = "requester" | "buyer" | "approver" | "admin";
export type ThemePreference = "light" | "dark" | "system";

export type IdentityPermissions = {
  canCreateRequisition: boolean;
  canViewSubmittedRequisitions: boolean;
  canUpdateOwnDraftRequisition: boolean;
  canSubmitOwnDraftRequisition: boolean;
  canAccessAdmin: boolean;
};

export type CurrentUserProfile = {
  id: string;
  name: string;
  email: string;
  avatarUrl: string | null;
  timezone: string;
  locale: string;
  theme: ThemePreference;
};

export type TenantMembershipSummary = {
  id: string;
  name: string;
  role: TenantRole;
};

export type ActiveTenantSummary = {
  id: string;
  name: string;
};

export type CurrentUserContext = {
  user: CurrentUserProfile;
  tenants: TenantMembershipSummary[];
  activeTenant: ActiveTenantSummary | null;
  activeRole: TenantRole | null;
  permissions: IdentityPermissions;
};

export type CurrentUserResponse = {
  data: CurrentUserContext;
};
