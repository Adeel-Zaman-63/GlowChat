package com.amizo.glowchat.ui.chats;

import android.app.Application;

import androidx.annotation.NonNull;
import androidx.lifecycle.AndroidViewModel;
import androidx.lifecycle.LiveData;

import com.amizo.glowchat.data.local.entity.ConversationEntity;
import com.amizo.glowchat.data.repository.ConversationRepository;

import java.util.List;

public class ConversationViewModel extends AndroidViewModel {

    private final ConversationRepository repository;

    public ConversationViewModel(@NonNull Application application) {
        super(application);
        repository = new ConversationRepository(application);
    }

    public LiveData<List<ConversationEntity>> observeAllConversations() {
        return repository.observeAllConversations();
    }

    public LiveData<List<ConversationEntity>> observeUnreadConversations() {
        return repository.observeUnreadConversations();
    }

    public LiveData<List<ConversationEntity>> observeFavoriteConversations() {
        return repository.observeFavoriteConversations();
    }

    public LiveData<List<ConversationEntity>> observeGroupConversations() {
        return repository.observeGroupConversations();
    }

    public LiveData<List<ConversationEntity>> observeArchivedConversations() {
        return repository.observeArchivedConversations();
    }

    public LiveData<ConversationEntity> observeConversation(String conversationId) {
        return repository.observeConversation(conversationId);
    }

    public void saveConversation(ConversationEntity conversation) {
        repository.saveConversation(conversation);
    }

    public void saveConversations(List<ConversationEntity> conversations) {
        repository.saveConversations(conversations);
    }

    public void deleteConversation(String conversationId) {
        repository.deleteConversation(conversationId);
    }
}
