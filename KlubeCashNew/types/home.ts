export type HomeUser = {
  name: string;
  type: string;
  avatarInitial: string;
  employeeSubtype: string | null;
  employeeSubtypeLabel: string | null;
  dashboardUrl: string;
  dashboardLabel: string;
};

export type PartnerStore = {
  name: string;
  category: string | null;
  logoUrl: string | null;
  fallback: {
    initial: string;
    startColor: string;
    endColor: string;
  };
};

export type HomeContext = {
  authenticated: boolean;
  user: HomeUser | null;
  partnerStores: PartnerStore[];
  links: {
    login: string;
    register: string;
    storeRegister: string;
    logout: string;
  };
  currentYear: number;
};
