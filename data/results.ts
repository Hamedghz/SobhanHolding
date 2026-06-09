import { kpis } from "./kpis";

export type SurveyResult = {
  id: number;
  employeeName: string;
  scores: Record<number, number>;
};

export const surveyResults: SurveyResult[] = [
  {
    id: 1,
    employeeName: "رضا محمدی",
    scores: { 1: 86, 2: 78, 3: 90, 4: 82 },
  },
  {
    id: 2,
    employeeName: "نگار سلیمی",
    scores: { 1: 72, 2: 88, 3: 74, 4: 80 },
  },
  {
    id: 3,
    employeeName: "امیر کاظمی",
    scores: { 1: 91, 2: 84, 3: 87, 4: 89 },
  },
];

export function calculateWeightedScore(scores: Record<number, number>) {
  const totalWeight = kpis.reduce((sum, kpi) => sum + kpi.weight, 0);
  const weightedTotal = kpis.reduce(
    (sum, kpi) => sum + (scores[kpi.id] ?? 0) * kpi.weight,
    0,
  );

  return Math.round(weightedTotal / totalWeight);
}
