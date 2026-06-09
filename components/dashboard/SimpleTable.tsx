import type { ReactNode } from "react";

type SimpleTableProps = {
  headers: string[];
  children: ReactNode;
};

export function SimpleTable({ headers, children }: SimpleTableProps) {
  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white text-sm shadow-sm">
      <table className="w-full min-w-[680px] border-collapse">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            {headers.map((header) => (
              <th key={header} className="px-4 py-3 text-right font-medium">
                {header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 text-slate-700">{children}</tbody>
      </table>
    </div>
  );
}
