import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, delay, map, of } from 'rxjs';
import { environment } from '../environments/environment';
import { ApiResponse } from '../app/core/models/api.models';
import { TessaAttachment, TessaConversationContext, TessaMessageApiResponse, TessaMessageRequest } from './tessa.models';

@Injectable({ providedIn: 'root' })
export class TessaService {
  private readonly endpoint = `${environment.apiUrl}/v1/merchant/tessa/messages`;

  constructor(private readonly http: HttpClient) {}

  send(message: string, context: TessaConversationContext, attachments: TessaAttachment[] = []): Observable<string> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.tessaAssistant) {
      const payload: TessaMessageRequest = { message, context: { route: context.route }, attachments: attachments.map((a) => ({ name: a.name, type: a.type, size: a.size })) };
      return this.http.post<ApiResponse<TessaMessageApiResponse>>(this.endpoint, payload).pipe(map((response) => response.data.reply));
    }

    return of(this.mockReply(message, context, attachments)).pipe(delay(650));
  }

  private mockReply(message: string, context: TessaConversationContext, attachments: TessaAttachment[]): string {
    const value = message.toLowerCase();
    const ack = attachments.length ? `Thanks for sharing ${attachments.length === 1 ? attachments[0].name : `those ${attachments.length} files`}. I'll factor that in. ` : '';
    if (!value && attachments.length) return `${ack}What would you like me to help you with?`;
    if (/balance|available/.test(value)) return `${ack}Your dashboard shows the latest available, ledger, and pending settlement balances. Open Overview for the current figures on account ${context.accountNumber}.`;
    if (/transaction|payment/.test(value)) return `${ack}Open Transactions to search by transaction number or reference, filter by status and date, and view full posting details.`;
    if (/settle|settlement/.test(value)) return `${ack}Settlements move cleared merchant funds to your linked MicroBiz account. Open Settlements to see what is available, request a settlement, and follow its status.`;
    if (/dispute|debited|failed/.test(value)) return `${ack}Open Reconciliation, select Disputes, and choose Raise dispute. You will need the merchant transaction number, a reason, and a short explanation.`;
    if (/report|statement|analytics/.test(value)) return `${ack}Reports & analytics lets you select a date range, compare sales by channel and location, and download a merchant statement.`;
    if (/link|qr/.test(value)) return `${ack}Merchant payment links and reusable QR tools are currently unavailable. You can still record supported QR and POS collections from Accept payment.`;
    if (/terminal|device|location/.test(value)) return `${ack}Locations & devices shows your outlets and terminal status. You can add a location or submit a terminal request there.`;
    if (/support|help|human/.test(value)) return `${ack}Open Support to create and track a ticket. For urgent account security issues, contact the merchant support team directly.`;
    return `${ack}I can help ${context.businessName} with transactions, settlements, reports, payment links, reconciliation, terminals, and support. What would you like to do?`;
  }
}
