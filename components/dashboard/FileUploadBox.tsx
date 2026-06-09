"use client";

import { useState } from "react";

export function FileUploadBox() {
  const [fileName, setFileName] = useState("");

  return (
    <label className="block rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center shadow-sm transition hover:border-blue-300 hover:bg-blue-50/30">
      <input
        type="file"
        className="sr-only"
        onChange={(event) => setFileName(event.target.files?.[0]?.name ?? "")}
      />
      <span className="text-sm font-medium text-slate-800">بارگذاری فایل جدید</span>
      <span className="mt-2 block text-xs text-slate-500">
        {fileName || "برای انتخاب فایل کلیک کنید."}
      </span>
    </label>
  );
}
