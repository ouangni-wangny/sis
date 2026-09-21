export type RondierPerimetre = {
  id: string;
  agent_id: string;
  zone_id: string | null;
  site_id: string | null;
  zone?: { id: string; nom: string } | null;
  site?: Site | null;
};

export type Agent = {
  id: string;
  nom: string;
  prenom: string;
  matricule: string;
  type: "agent" | "controleur" | "administration";
  statut: string;
  grade?: { id: string; libelle: string } | null;
  perimetres?: RondierPerimetre[];
};

export type MobileUser = {
  id: string;
  nom: string;
  prenom: string;
  matricule: string;
  type: string;
  roles: string[];
  permissions: string[];
  agent?: Agent | null;
};

export type Site = {
  id: string;
  nom: string;
  adresse?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  rayon_metres?: number | null;
};

export type Vacation = {
  id: string;
  agent_id: string;
  site_id: string;
  poste_id?: string | null;
  date_debut: string;
  date_fin?: string | null;
  heure_debut: string;
  heure_fin: string;
  statut: string;
  agent?: Agent | null;
  site?: Site | null;
  poste?: {
    id: string;
    nom: string;
    heure_debut?: string | null;
    heure_fin?: string | null;
    heure_debut_nuit?: string | null;
    heure_fin_nuit?: string | null;
  } | null;
};

export type ControleResultat = "present" | "absent" | "enregistre";

export type Controle = {
  id: string;
  agent_id: string;
  controle_agent_id: string;
  site_id: string;
  poste_id?: string | null;
  effectue_at?: string | null;
  commentaire?: string | null;
  resultat: ControleResultat;
  controle_agent?: Agent | null;
  site?: Site | null;
};

export type Paginated<T> = {
  data: T[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type DataResponse<T> = { data: T };
