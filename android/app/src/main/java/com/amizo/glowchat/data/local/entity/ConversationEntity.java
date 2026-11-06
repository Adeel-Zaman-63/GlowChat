package com.amizo.glowchat.data.local.entity;

import androidx.annotation.NonNull;
import androidx.room.Entity;
import androidx.room.Index;
import androidx.room.PrimaryKey;

@Entity(
        tableName = "conversations",
        indices = {
                @Index(value = {"type"}),
                @Index(value = {"lastActivityAt"}),
                @Index(value = {"isArchived"}),
                @Index(value = {"isFavorite"}),
                @Index(value = {"isPinned", "pinnedOrder"})
        }
)
public class ConversationEntity {

    public static final String TYPE_PRIVATE = "PRIVATE";
    public static final String TYPE_GROUP = "GROUP";

    @PrimaryKey
    @NonNull
    public String conversationId;

    @NonNull
    public String type;

    public String title;

    public String avatarPath;

    public long lastActivityAt;

    public int unreadCount;

    public boolean isPinned;

    public boolean isFavorite;

    public int pinnedOrder;

    public boolean isArchived;

    public long lastSyncedSequence;

    public long createdAt;

    public long updatedAt;

    public ConversationEntity(
            @NonNull String conversationId,
            @NonNull String type,
            String title,
            String avatarPath,
            long lastActivityAt,
            int unreadCount,
            boolean isPinned,
            boolean isFavorite,
            int pinnedOrder,
            boolean isArchived,
            long lastSyncedSequence,
            long createdAt,
            long updatedAt
    ) {
        this.conversationId = conversationId;
        this.type = type;
        this.title = title;
        this.avatarPath = avatarPath;
        this.lastActivityAt = lastActivityAt;
        this.unreadCount = unreadCount;
        this.isPinned = isPinned;
        this.isFavorite = isFavorite;
        this.pinnedOrder = pinnedOrder;
        this.isArchived = isArchived;
        this.lastSyncedSequence = lastSyncedSequence;
        this.createdAt = createdAt;
        this.updatedAt = updatedAt;
    }
}
