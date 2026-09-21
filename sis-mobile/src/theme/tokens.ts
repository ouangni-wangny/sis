export const colors = {
  teal: "#0F6B5C",
  tealDark: "#0A4F44",
  tealLight: "#2A9B88",
  tealSoft: "#E6F2EF",
  ink: "#0B1F1C",
  inkMuted: "#3D524E",
  inkFaint: "#7A8F89",
  paper: "#F2F5F4",
  paperMuted: "#E8ECEA",
  border: "#D5DDD9",
  danger: "#B42318",
  dangerSoft: "#FCEBEA",
  warning: "#B54708",
  warningSoft: "#FEF0C7",
  success: "#0F6B5C",
  white: "#FFFFFF",
  canvasTop: "#EEF2F0",
  canvasBottom: "#F7F8F7",
  overlay: "rgba(11, 31, 28, 0.55)",
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
} as const;

export const radii = {
  sm: 10,
  md: 14,
  lg: 20,
  xl: 28,
  full: 999,
} as const;

export const shadows = {
  card: {
    shadowColor: "#0B1F1C",
    shadowOpacity: 0.06,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 8 },
    elevation: 3,
  },
  soft: {
    shadowColor: "#0B1F1C",
    shadowOpacity: 0.04,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
} as const;

export const typography = {
  brand: {
    fontFamily: "Manrope_800ExtraBold",
    fontSize: 32,
    letterSpacing: -0.8,
  },
  title: {
    fontFamily: "Manrope_700Bold",
    fontSize: 24,
    letterSpacing: -0.4,
  },
  subtitle: {
    fontFamily: "Manrope_600SemiBold",
    fontSize: 17,
  },
  body: {
    fontFamily: "Manrope_400Regular",
    fontSize: 15,
    lineHeight: 22,
  },
  bodyStrong: {
    fontFamily: "Manrope_600SemiBold",
    fontSize: 15,
  },
  caption: {
    fontFamily: "Manrope_500Medium",
    fontSize: 12,
    letterSpacing: 0.2,
  },
  mono: {
    fontFamily: "IBMPlexMono_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
  },
} as const;
