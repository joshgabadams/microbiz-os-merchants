export interface TessaMessage {
  id: string;
  role: 'assistant' | 'user';
  content: string;
  createdAt: string;
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
}

export interface TessaMessageApiResponse {
  reply: string;
  conversation_id: string;
}
