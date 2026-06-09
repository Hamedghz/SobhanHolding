type ProgressBarProps = {
  value: number;
};

export function ProgressBar({ value }: ProgressBarProps) {
  const color =
    value >= 85 ? "bg-green-600" : value >= 70 ? "bg-amber-500" : "bg-red-600";

  return (
    <div className="flex items-center gap-3">
      <div className="h-2 w-28 overflow-hidden rounded-full bg-slate-100">
        <div className={`h-full rounded-full ${color}`} style={{ width: `${value}%` }} />
      </div>
      <span className="w-8 text-xs font-medium text-slate-700">{value}</span>
    </div>
  );
}
