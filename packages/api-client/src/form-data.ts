export type BuildFormDataOptions = {
  useBracketNotation?: boolean;
};

export function buildFormData(body: object, options: BuildFormDataOptions = {}): FormData {
  const formData = new FormData();

  for (const [key, value] of Object.entries(body)) {
    appendFormDataValue(formData, key, value, options);
  }

  return formData;
}

function appendFormDataValue(
  formData: FormData,
  key: string,
  value: unknown,
  options: BuildFormDataOptions,
): void {
  if (value === undefined || value === null) {
    return;
  }

  if (Array.isArray(value)) {
    const arrayKey = options.useBracketNotation ? `${key}[]` : key;
    value.forEach((item) => appendFormDataValue(formData, arrayKey, item, options));
    return;
  }

  if (value instanceof Blob) {
    if (isFile(value) && value.name.length > 0) {
      const filename = value.name;
      formData.append(key, value, filename);
      appendFileMetadata(formData, key, filename, value);
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

function appendFileMetadata(
  formData: FormData,
  fieldKey: string,
  filename: string,
  value: Blob,
): void {
  formData.append(`${fieldKey}.filename`, filename);
  formData.append(`${fieldKey}.mimeType`, value.type);
  formData.append(`${fieldKey}.sizeBytes`, value.size.toString());
}

function isFile(value: Blob): value is File {
  if (typeof File !== "undefined" && value instanceof File) {
    return true;
  }

  if (
    typeof window !== "undefined" &&
    typeof window.File !== "undefined" &&
    value instanceof window.File
  ) {
    return true;
  }

  return (
    Object.prototype.toString.call(value) === "[object File]" ||
    ("name" in value && typeof value.name === "string")
  );
}
