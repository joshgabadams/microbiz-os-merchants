import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from './auth.service';
import { MerchantPortalSessionService } from './merchant-portal-session.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const merchantSession = inject(MerchantPortalSessionService);
  const isMerchantSelfServiceRequest = /\/api\/v1\/merchant(?:\/|$)/.test(req.url);
  const merchantToken = merchantSession.getAccessToken();
  const credential = auth.getCredential();

  if (isMerchantSelfServiceRequest && merchantToken) {
    return next(req.clone({
      setHeaders: {
        Authorization: `Bearer ${merchantToken}`,
        Accept: 'application/json',
      },
    }));
  }

  if (!credential) {
    return next(req);
  }

  const authReq = req.clone({
    setHeaders: {
      Authorization: `Basic ${credential}`,
      Accept: 'application/json',
    },
  });

  return next(authReq);
};
