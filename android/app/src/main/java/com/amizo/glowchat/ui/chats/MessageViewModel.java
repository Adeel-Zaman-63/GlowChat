package com.amizo.glowchat.ui.chats;

import android.app.Application;

import androidx.annotation.NonNull;
import androidx.lifecycle.AndroidViewModel;
import androidx.lifecycle.LiveData;

import com.amizo.glowchat.data.local.entity.MessageEntity;
import com.amizo.glowchat.data.repository.MessageRepository;

import java.util.List;

public class MessageViewModel extends AndroidViewModel {

    private final MessageRepository repository;

    public MessageViewModel(@NonNull Application application) {
        super(application);
        repository = new MessageRepository(application);
    }

    public LiveData<List<MessageEntity>> observeMessages(
            String conversationId
    ) {
        return repository.observeMessages(conversationId);
    }

    public List<MessageEntity> getLatestMessages(
            String conversationId,
            int limit
    ) {
        return repository.getLatestMessages(conversationId, limit);
    }

    public List<MessageEntity> getOlderMessages(
            String conversationId,
            long beforeSequence,
            int limit
    ) {
        return repository.getOlderMessages(
                conversationId,
                beforeSequence,
                limit
        );
    }

    public LiveData<MessageEntity> observeMessage(String messageId) {
        return repository.observeMessage(messageId);
    }

    public void saveMessage(MessageEntity message) {
        repository.saveMessage(message);
    }

    public void saveMessages(List<MessageEntity> messages) {
        repository.saveMessages(messages);
    }

    public void updateMessageStatus(
            String messageId,
            String status,
            long updatedAt
    ) {
        repository.updateMessageStatus(messageId, status, updatedAt);
    }

    public void markMessageAsSent(
            String messageId,
            long sequenceNumber,
            long serverCreatedAt,
            long updatedAt
    ) {
        repository.markMessageAsSent(
                messageId,
                sequenceNumber,
                serverCreatedAt,
                updatedAt
        );
    }

    public void deleteMessage(String messageId) {
        repository.deleteMessage(messageId);
    }

    public void deleteConversationMessages(String conversationId) {
        repository.deleteConversationMessages(conversationId);
    }
}
