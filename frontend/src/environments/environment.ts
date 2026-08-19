export const environment = {
  production: false,
  apiUrl: '/api',
  merchantPortalApiMode: 'live' as 'mock' | 'live',
  merchantPortalLiveFeatures: {
    accountPreview: true,
    otp: false,
    registration: false,
    merchantProfile: false,
    merchantSession: false,
  },
};
