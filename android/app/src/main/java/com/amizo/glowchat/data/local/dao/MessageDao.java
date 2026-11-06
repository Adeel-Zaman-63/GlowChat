package com.amizo.glowchat.data.local.dao;

import androidx.lifecycle.LiveData;
import androidx.room.Dao;
import androidx.room.Query;
import androidx.room.Upsert;

import com.amizo.glowchat.data.local.entity.MessageEntity;

import java.util.List;

@Dao
public interface MessageDao {

    // Observe messages in chronological order.
    @Query("SELECT * FROM messages " +
            "WHERE conversationId = :conversationId " +
            "ORDER BY sequenceNumber ASC, clientCreatedAt ASC")
    LiveData<List<MessageEntity>> observeMessages(String conversationId);

    // Load the latest messages for a conversation.
    @Query("SELECT * FROM messages " +
            "WHERE conversationId = :conversationId " +
            "ORDER BY sequenceNumber DESC, clientCreatedAt DESC " +
            "LIMIT :limit")
    List<MessageEntity> getLatestMessages(String conversationId, int limit);

    // Load older messages using the server sequence number.
    @Query("SELECT * FROM messages " +
            "WHERE conversationId = :conversationId " +
            "AND sequenceNumber > 0 " +
            "AND sequenceNumber < :beforeSequence " +
            "ORDER BY sequenceNumber DESC " +
            "LIMIT :limit")
    List<MessageEntity> getOlderMessages(
            String conversationId,
            long beforeSequence,
            int limit
    );

    // Find a message using its unique ID.
    @Query("SELECT * FROM messages WHERE messageId = :messageId LIMIT 1")
    LiveData<MessageEntity> observeMessage(String messageId);

    // Find a message synchronously.
    @Query("SELECT * FROM messages WHERE messageId = :messageId LIMIT 1")
    MessageEntity getMessage(String messageId);

    // Save or update messages without deleting existing rows first.
    @Upsert
    void insertOrUpdate(MessageEntity message);

    @Upsert
    void insertOrUpdateAll(List<MessageEntity> messages);

    // Update the local sending state.
    @Query("UPDATE messages SET localStatus = :status, updatedAt = :updatedAt " +
            "WHERE messageId = :messageId")
    void updateMessageStatus(
            String messageId,
            String status,
            long updatedAt
    );

    // Apply the server-assigned sequence number.
    @Query("UPDATE messages SET sequenceNumber = :sequenceNumber, " +
            "serverCreatedAt = :serverCreatedAt, " +
            "localStatus = 'SENT', updatedAt = :updatedAt " +
            "WHERE messageId = :messageId")
    void markMessageAsSent(
            String messageId,
            long sequenceNumber,
            long serverCreatedAt,
            long updatedAt
    );

    // Delete one local message.
    @Query("DELETE FROM messages WHERE messageId = :messageId")
    void deleteMessage(String messageId);

    // Delete all local messages belonging to one conversation.
    @Query("DELETE FROM messages WHERE conversationId = :conversationId")
    void deleteConversationMessages(String conversationId);

    // Delete all local messages.
    @Query("DELETE FROM messages")
    void deleteAllMessages();
}
