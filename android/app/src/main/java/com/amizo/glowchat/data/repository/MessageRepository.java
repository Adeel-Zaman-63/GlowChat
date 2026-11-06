package com.amizo.glowchat.data.repository;

import android.content.Context;

import androidx.lifecycle.LiveData;

import com.amizo.glowchat.data.local.dao.MessageDao;
import com.amizo.glowchat.data.local.database.GlowChatDatabase;
import com.amizo.glowchat.data.local.entity.MessageEntity;

import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class MessageRepository {

    private final MessageDao messageDao;

    private final ExecutorService databaseExecutor =
            Executors.newSingleThreadExecutor();

    public MessageRepository(Context context) {
        GlowChatDatabase database =
                GlowChatDatabase.getInstance(context);

        messageDao = database.messageDao();
    }

    public LiveData<List<MessageEntity>> observeMessages(
            String conversationId
    ) {
        return messageDao.observeMessages(conversationId);
    }

    public List<MessageEntity> getLatestMessages(
            String conversationId,
            int limit
    ) {
        return messageDao.getLatestMessages(conversationId, limit);
    }

    public List<MessageEntity> getOlderMessages(
            String conversationId,
            long beforeSequence,
            int limit
    ) {
        return messageDao.getOlderMessages(
                conversationId,
                beforeSequence,
                limit
        );
    }

    public LiveData<MessageEntity> observeMessage(String messageId) {
        return messageDao.observeMessage(messageId);
    }

    public MessageEntity getMessage(String messageId) {
        return messageDao.getMessage(messageId);
    }

    public void saveMessage(MessageEntity message) {
        databaseExecutor.execute(
                () -> messageDao.insertOrUpdate(message)
        );
    }

    public void saveMessages(List<MessageEntity> messages) {
        databaseExecutor.execute(
                () -> messageDao.insertOrUpdateAll(messages)
        );
    }

    public void updateMessageStatus(
            String messageId,
            String status,
            long updatedAt
    ) {
        databaseExecutor.execute(
                () -> messageDao.updateMessageStatus(
                        messageId,
                        status,
                        updatedAt
                )
        );
    }

    public void markMessageAsSent(
            String messageId,
            long sequenceNumber,
            long serverCreatedAt,
            long updatedAt
    ) {
        databaseExecutor.execute(
                () -> messageDao.markMessageAsSent(
                        messageId,
                        sequenceNumber,
                        serverCreatedAt,
                        updatedAt
                )
        );
    }

    public void deleteMessage(String messageId) {
        databaseExecutor.execute(
                () -> messageDao.deleteMessage(messageId)
        );
    }

    public void deleteConversationMessages(String conversationId) {
        databaseExecutor.execute(
                () -> messageDao.deleteConversationMessages(conversationId)
        );
    }

    public void deleteAllMessages() {
        databaseExecutor.execute(
                messageDao::deleteAllMessages
        );
    }
}
