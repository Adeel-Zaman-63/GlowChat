package com.amizo.glowchat.ui.chats;

public class ChatMessage {

    public static final String STATUS_PENDING = "PENDING";
    public static final String STATUS_SENT = "SENT";
    public static final String STATUS_DELIVERED = "DELIVERED";
    public static final String STATUS_READ = "READ";
    public static final String STATUS_FAILED = "FAILED";

    private final String messageId;
    private final String body;
    private final long createdAt;
    private final boolean outgoing;
    private final String status;

    public ChatMessage(
            String messageId,
            String body,
            long createdAt,
            boolean outgoing,
            String status
    ) {
        this.messageId = messageId;
        this.body = body;
        this.createdAt = createdAt;
        this.outgoing = outgoing;
        this.status = status;
    }

    public String getMessageId() {
        return messageId;
    }

    public String getBody() {
        return body;
    }

    public long getCreatedAt() {
        return createdAt;
    }

    public boolean isOutgoing() {
        return outgoing;
    }

    public String getStatus() {
        return status;
    }
}
