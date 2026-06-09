type StatCardProps = {
  title: string;
  value: string;
  helper: string;
};

export function StatCard({ title, value, helper }: StatCardProps) {
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p className="text-sm text-slate-500">{title}</p>
      <p className="mt-3 text-2xl font-bold text-slate-950">{value}</p>
      <p className="mt-2 text-xs leading-6 text-slate-500">{helper}</p>
    </section>
  );
}
