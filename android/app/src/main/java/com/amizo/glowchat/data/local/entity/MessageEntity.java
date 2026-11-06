package com.amizo.glowchat.data.local.entity;

import androidx.annotation.NonNull;
import androidx.room.Entity;
import androidx.room.ForeignKey;
import androidx.room.Index;
import androidx.room.PrimaryKey;

@Entity(
        tableName = "messages",
        foreignKeys = {
                @ForeignKey(
                        entity = ConversationEntity.class,
                        parentColumns = "conversationId",
                        childColumns = "conversationId",
                        onDelete = ForeignKey.CASCADE
                )
        },
        indices = {
                @Index(value = {"conversationId", "sequenceNumber"}),
                @Index(value = {"conversationId", "clientCreatedAt"}),
                @Index(value = {"senderUserId"})
        }
)
public class MessageEntity {

    // Supported message types.
    public static final String TYPE_TEXT = "TEXT";
    public static final String TYPE_IMAGE = "IMAGE";
    public static final String TYPE_VIDEO = "VIDEO";
    public static final String TYPE_AUDIO = "AUDIO";
    public static final String TYPE_VOICE = "VOICE";
    public static final String TYPE_DOCUMENT = "DOCUMENT";
    public static final String TYPE_STICKER = "STICKER";
    public static final String TYPE_LOCATION = "LOCATION";
    public static final String TYPE_CONTACT = "CONTACT";
    public static final String TYPE_POLL = "POLL";
    public static final String TYPE_SYSTEM = "SYSTEM";

    // Local synchronization states.
    public static final String STATUS_PENDING = "PENDING";
    public static final String STATUS_SENT = "SENT";
    public static final String STATUS_FAILED = "FAILED";

    @PrimaryKey
    @NonNull
    public String messageId;

    @NonNull
    public String conversationId;

    @NonNull
    public String senderUserId;

    @NonNull
    public String messageType;

    @NonNull
    public byte[] encryptedPayload;

    public int encryptionVersion;

    // Zero means the server has not assigned a sequence number yet.
    public long sequenceNumber;

    // All timestamps are stored as Unix epoch milliseconds.
    public long clientCreatedAt;

    public long serverCreatedAt;

    @NonNull
    public String localStatus;

    // Null when this message is not a reply.
    public String replyToMessageId;

    // Zero means the message has not been edited.
    public long editedAt;

    // Zero means the message does not expire.
    public long expiresAt;

    public long createdAt;

    public long updatedAt;

    public MessageEntity(
            @NonNull String messageId,
            @NonNull String conversationId,
            @NonNull String senderUserId,
            @NonNull String messageType,
            @NonNull byte[] encryptedPayload,
            int encryptionVersion,
            long sequenceNumber,
            long clientCreatedAt,
            long serverCreatedAt,
            @NonNull String localStatus,
            String replyToMessageId,
            long editedAt,
            long expiresAt,
            long createdAt,
            long updatedAt
    ) {
        this.messageId = messageId;
        this.conversationId = conversationId;
        this.senderUserId = senderUserId;
        this.messageType = messageType;
        this.encryptedPayload = encryptedPayload;
        this.encryptionVersion = encryptionVersion;
        this.sequenceNumber = sequenceNumber;
        this.clientCreatedAt = clientCreatedAt;
        this.serverCreatedAt = serverCreatedAt;
        this.localStatus = localStatus;
        this.replyToMessageId = replyToMessageId;
        this.editedAt = editedAt;
        this.expiresAt = expiresAt;
        this.createdAt = createdAt;
        this.updatedAt = updatedAt;
    }
}
