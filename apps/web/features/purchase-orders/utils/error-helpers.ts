export function errorToMessage(error: unknown): string | null {
  if (error && typeof error === "object") {
    const message =
      (error as { error?: { message?: string }; message?: string }).error?.message ?? (error as { message?: string }).message;
    if (message) return message;
  }

  return null;
}
