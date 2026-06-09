export type UserRole = "Admin" | "Manager";
export type UserStatus = "فعال" | "غیرفعال";

export type User = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  status: UserStatus;
};

export const users: User[] = [
  {
    id: 1,
    name: "علی احمدی",
    email: "ali.ahmadi@sobhan.local",
    role: "Admin",
    status: "فعال",
  },
  {
    id: 2,
    name: "مریم رضایی",
    email: "maryam.rezaei@sobhan.local",
    role: "Manager",
    status: "فعال",
  },
  {
    id: 3,
    name: "حسین کریمی",
    email: "hossein.karimi@sobhan.local",
    role: "Manager",
    status: "غیرفعال",
  },
  {
    id: 4,
    name: "سمانه نوری",
    email: "samane.noori@sobhan.local",
    role: "Manager",
    status: "فعال",
  },
];
