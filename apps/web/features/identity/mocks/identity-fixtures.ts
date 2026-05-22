import type { CurrentUserContext } from "../types/identity-view-model";
import { defaultNotificationPreferences } from "../schemas/profile-schema";

const cloneNotificationPreferences = () => structuredClone(defaultNotificationPreferences);

export const requesterIdentity: CurrentUserContext = {
  user: {
    id: "1",
    name: "Test User",
    email: "test@example.com",
    avatarUrl: null,
    timezone: "Asia/Kuala_Lumpur",
    locale: "en",
    theme: "system",
    notificationPreferences: cloneNotificationPreferences(),
  },
  tenants: [{ id: "1", name: "Acme Procurement", role: "requester" }],
  activeTenant: { id: "1", name: "Acme Procurement" },
  activeRole: "requester",
  permissions: {
    canCreateRequisition: true,
    canViewSubmittedRequisitions: false,
    canUpdateOwnDraftRequisition: true,
    canSubmitOwnDraftRequisition: true,
    canAccessAdmin: false,
    canManageSourcingIntake: false,
    canReviewQuotationNormalization: false,
  },
};

export const multiTenantIdentity: CurrentUserContext = {
  ...requesterIdentity,
  tenants: [
    { id: "1", name: "Acme Procurement", role: "requester" },
    { id: "2", name: "Northwind Sourcing", role: "buyer" },
  ],
  activeTenant: null,
  activeRole: null,
  permissions: {
    canCreateRequisition: false,
    canViewSubmittedRequisitions: false,
    canUpdateOwnDraftRequisition: false,
    canSubmitOwnDraftRequisition: false,
    canAccessAdmin: false,
    canManageSourcingIntake: false,
    canReviewQuotationNormalization: false,
  },
};
