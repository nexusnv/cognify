export function buildFormData(body: object): FormData {
  const formData = new FormData();

  for (const [key, value] of Object.entries(body)) {
    appendFormDataValue(formData, key, value);
  }

  return formData;
}

function appendFormDataValue(formData: FormData, key: string, value: unknown): void {
  if (value === undefined || value === null) {
    return;
  }

  if (Array.isArray(value)) {
    value.forEach((item) => appendFormDataValue(formData, key, item));
    return;
  }

  if (value instanceof Blob) {
    const filename = (value as { name?: unknown }).name;
    if (typeof filename === "string" && filename.length > 0) {
      formData.append(key, value, filename);
      appendFileMetadata(formData, filename, value);
      return;
    }

    formData.append(key, value);
    return;
  }

  if (value instanceof Date) {
    formData.append(key, value.toISOString());
    return;
  }

  if (typeof value === "object") {
    formData.append(key, JSON.stringify(value));
    return;
  }

  formData.append(key, String(value));
}

function appendFileMetadata(formData: FormData, filename: string, value: Blob): void {
  if (!formData.has("filename")) {
    formData.append("filename", filename);
  }

  if (!formData.has("mimeType")) {
    formData.append("mimeType", value.type);
  }

  if (!formData.has("sizeBytes")) {
    formData.append("sizeBytes", value.size.toString());
  }
}
