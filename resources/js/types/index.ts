import { LOCALE_CODE } from "@/shared/constants/variables";

export type LOCALE_TYPE = typeof LOCALE_CODE[keyof typeof LOCALE_CODE];
