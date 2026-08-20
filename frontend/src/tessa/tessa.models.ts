export interface TessaAttachment {
  name: string;
  type: string;
  size: number;
  previewUrl?: string;
}

export interface TessaMessage {
  id: string;
  role: 'assistant' | 'user';
  content: string;
  createdAt: string;
  attachments?: TessaAttachment[];
}

export interface TessaConversationContext {
  route: string;
  businessName: string;
  accountNumber: string;
}

export interface TessaMessageRequest {
  message: string;
  context: {
    route: string;
  };
  attachments?: Array<{ name: string; type: string; size: number }>;
}

export interface TessaMessageApiResponse {
  reply: string;
  conversation_id: string;
}
