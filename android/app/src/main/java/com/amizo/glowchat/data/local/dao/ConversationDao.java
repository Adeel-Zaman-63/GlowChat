package com.amizo.glowchat.data.local.dao;

import androidx.lifecycle.LiveData;
import androidx.room.Dao;
import androidx.room.Insert;
import androidx.room.OnConflictStrategy;
import androidx.room.Query;

import com.amizo.glowchat.data.local.entity.ConversationEntity;

import java.util.List;

@Dao
public interface ConversationDao {

    @Query("SELECT * FROM conversations " +
            "WHERE isArchived = 0 " +
            "ORDER BY isPinned DESC, pinnedOrder ASC, lastActivityAt DESC")
    LiveData<List<ConversationEntity>> observeAllConversations();

    @Query("SELECT * FROM conversations " +
            "WHERE isArchived = 0 AND unreadCount > 0 " +
            "ORDER BY lastActivityAt DESC")
    LiveData<List<ConversationEntity>> observeUnreadConversations();

    @Query("SELECT * FROM conversations " +
            "WHERE isArchived = 0 AND isPinned = 1 " +
            "ORDER BY pinnedOrder ASC")
    LiveData<List<ConversationEntity>> observePinnedConversations();

    @Query("SELECT * FROM conversations " +
            "WHERE isArchived = 0 AND isFavorite = 1 " +
            "ORDER BY lastActivityAt DESC")
    LiveData<List<ConversationEntity>> observeFavoriteConversations();

    @Query("SELECT * FROM conversations " +
            "WHERE isArchived = 0 AND type = 'GROUP' " +
            "ORDER BY lastActivityAt DESC")
    LiveData<List<ConversationEntity>> observeGroupConversations();

    @Query("SELECT * FROM conversations " +
            "WHERE isArchived = 1 " +
            "ORDER BY lastActivityAt DESC")
    LiveData<List<ConversationEntity>> observeArchivedConversations();

    @Query("SELECT * FROM conversations " +
            "WHERE conversationId = :conversationId LIMIT 1")
    LiveData<ConversationEntity> observeConversation(String conversationId);

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertOrUpdate(ConversationEntity conversation);

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertOrUpdateAll(List<ConversationEntity> conversations);

    @Query("DELETE FROM conversations WHERE conversationId = :conversationId")
    void deleteConversation(String conversationId);

    @Query("DELETE FROM conversations")
    void deleteAllConversations();
}
