import { SizeDefinition } from '../components/size_settings';

type PlacementStyles = {
  width: SizeDefinition;
};

type FormPlacementBase = {
  enabled: boolean;
  styles: PlacementStyles;
  categories: string[] | number[];
  tags: string[] | number[];
  posts: { all: boolean | '' | '1'; selected: string[] };
  pages: { all: boolean | '' | '1'; selected: string[] };
};

export type FormSettingsType = {
  alignment: string;
  backgroundImageDisplay?: string;
  backgroundImageUrl?: string;
  belowPostStyles: PlacementStyles;
  borderColor?: string;
  borderRadius: number;
  borderSize: number;
  errorValidationColor?: string;
  fixedBarFormDelay: number;
  fixedBarFormCookieExpiration: number;
  fixedBarFormPosition: string;
  fixedBarStyles: PlacementStyles;
  fontFamily?: string;
  formPadding: number;
  formPlacement: {
    popup: FormPlacementBase & {
      exitIntentEnabled: boolean;
      delay: number | `${number}`;
      cookieExpiration: number | `${number}`;
      animation: string;
    };
    fixedBar: FormPlacementBase & {
      delay: number;
      cookieExpiration: number;
      animation: string;
      position: 'top' | 'bottom';
    };
    belowPosts: FormPlacementBase;
    slideIn: FormPlacementBase & {
      delay: number;
      cookieExpiration: number;
      animation: string;
      position: 'left' | 'right';
    };
    others: {
      styles: PlacementStyles;
    };
  };
  inputPadding: number;
  otherStyles: PlacementStyles;
  placeFixedBarFormOnAllPages: boolean;
  placeFixedBarFormOnAllPosts: boolean;
  placeFormBellowAllPages: boolean;
  placeFormBellowAllPosts: boolean;
  placePopupFormOnAllPages: boolean;
  placePopupFormOnAllPosts: boolean;
  placeSlideInFormOnAllPages: boolean;
  placeSlideInFormOnAllPosts: boolean;
  popupFormDelay: number;
  popupFormCookieExpiration: number;
  popupStyles: PlacementStyles;
  segments: Array<string>;
  slideInFormDelay: number;
  slideInFormCookieExpiration: number;
  slideInFormPosition: string;
  slideInStyles: PlacementStyles;
  successValidationColor?: string;
};

export type InputBlockStyles = {
  fullWidth: boolean;
  inheritFromTheme: boolean;
  bold?: boolean;
  backgroundColor?: string;
  gradient?: string;
  borderSize?: number;
  fontSize?: number;
  fontColor?: string;
  borderRadius?: number;
  borderColor?: string;
  padding?: number;
  fontFamily?: string;
};

export type InputBlockStylesServerData = {
  full_width: boolean | string;
  bold?: boolean | string;
  background_color?: string;
  gradient?: string;
  border_size?: string | number;
  font_size?: string | number;
  font_color?: string;
  border_radius?: string | number;
  border_color?: string;
  padding?: string | number;
  font_family?: string;
};

export type ColorDefinition = {
  name: string;
  slug: string;
  color: string;
};

export type GradientDefinition = {
  name: string;
  slug: string;
  gradient: string;
};

export type FontSizeDefinition = {
  name: string;
  slug: string;
  size: number;
};
